<?php

namespace Tests\Feature;

use App\Mail\RegistrationCancelled;
use App\Models\Activity;
use App\Models\Registration;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class CancellationTest extends TestCase
{
    use RefreshDatabase;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    /** @param array<string, mixed> $attributes */
    private function registration(Activity $activity, string $name, ?string $status = null, array $attributes = []): Registration
    {
        $registration = $activity->registrations()->create(['name' => $name, 'email' => strtolower($name).'@example.com', 'answers' => [], 'amount_cents' => $activity->price_cents]);

        if ($status) {
            $paymentId = 'tr_'.strtolower($name);
            $registration->forceFill(['payment_status' => $status, 'mollie_payment_id' => $paymentId] + $attributes)->save();
            $this->gateway->statuses[$paymentId] = $status;
        }

        return $registration;
    }

    private function paidActivity(): Activity
    {
        return Activity::factory()->create(['price_cents' => 1250, 'title' => 'Gouwweekend']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    private function cancel(Registration $registration, array $data = []): TestResponse
    {
        return $this->actingAs($registration->activity->user)
            ->from("/activiteiten/{$registration->activity->slug}/inschrijvingen")
            ->delete("/activiteiten/{$registration->activity->slug}/inschrijvingen/{$registration->id}", $data);
    }

    public function test_the_dialog_for_a_paid_registration_offers_both_refunds(): void
    {
        $registration = $this->registration($this->paidActivity(), 'Bert', 'paid');

        $this->actingAs($registration->activity->user)
            ->get("/activiteiten/{$registration->activity->slug}/inschrijvingen")
            ->assertSee('data-confirm="Inschrijving van Bert annuleren?"', false)
            ->assertSee('data-confirm-button="Annuleren en terugbetalen"', false)
            ->assertSee('name="refund" value="full" form="annuleer-'.$registration->id.'" checked', false)
            ->assertSee('Terugbetalen min administratiekosten')
            ->assertSee('name="admin_cost" value="1,00"', false)
            ->assertSee('binnen enkele werkdagen teruggestort');
    }

    public function test_cancelling_a_paid_registration_with_a_full_refund(): void
    {
        $registration = $this->registration($this->paidActivity(), 'Bert', 'paid');

        $this->cancel($registration, ['refund' => 'full'])
            ->assertRedirect()
            ->assertSessionHas('status', 'De inschrijving van Bert is geannuleerd. De terugbetaling van € 12,50 wordt verwerkt.');

        $this->assertSame([['id' => 're_test1', 'payment' => 'tr_bert', 'amount' => 1250, 'description' => 'Terugbetaling Gouwweekend – Bert']], $this->gateway->refunds);
        $this->assertModelMissing($registration);
        Mail::assertSent(RegistrationCancelled::class, fn ($mail) => $mail->hasTo('bert@example.com')
            && $mail->refundedCents === 1250 && $mail->adminCostCents === 0 && ! $mail->activityCancelled);
    }

    public function test_cancelling_with_administrative_costs(): void
    {
        $activity = $this->paidActivity();

        $this->cancel($this->registration($activity, 'Bert', 'paid'), ['refund' => 'minus_costs', 'admin_cost' => '1,00']);
        $this->cancel($this->registration($activity, 'Olga', 'paid'), ['refund' => 'minus_costs', 'admin_cost' => '2.5']);

        $this->assertSame([1150, 1000], array_column($this->gateway->refunds, 'amount'));
        Mail::assertSent(RegistrationCancelled::class, fn ($mail) => $mail->hasTo('bert@example.com') && $mail->refundedCents === 1150 && $mail->adminCostCents === 100);
    }

    public function test_administrative_costs_must_be_a_valid_amount_below_the_price(): void
    {
        $registration = $this->registration($this->paidActivity(), 'Bert', 'paid');

        $this->cancel($registration, ['refund' => 'minus_costs', 'admin_cost' => '12,50'])->assertSessionHasErrors('cancel');
        $this->cancel($registration, ['refund' => 'minus_costs', 'admin_cost' => 'veel'])->assertSessionHasErrors('admin_cost');
        $this->cancel($registration, ['refund' => 'minus_costs', 'admin_cost' => '-1'])->assertSessionHasErrors('admin_cost');
        $this->cancel($registration, [])->assertSessionHasErrors('refund');

        $this->assertSame([], $this->gateway->refunds);
        $this->assertModelExists($registration);
        Mail::assertNothingSent();
    }

    public function test_a_refused_refund_keeps_the_registration(): void
    {
        $registration = $this->registration($this->paidActivity(), 'Bert', 'paid');
        $this->gateway->refusedRefunds[] = 'tr_bert';

        $this->followingRedirects()
            ->cancel($registration, ['refund' => 'full'])
            ->assertOk()
            ->assertSee('De terugbetaling aan Bert lukte niet, dus de inschrijving is niet geannuleerd.');

        $this->assertModelExists($registration);
        Mail::assertNothingSent();
    }

    public function test_a_payment_still_at_the_bank_cannot_be_cancelled_yet(): void
    {
        $registration = $this->registration($this->paidActivity(), 'Bert', 'pending');

        $this->cancel($registration, ['refund' => 'full'])->assertSessionHasErrors('cancel');

        $this->assertModelExists($registration);
        $this->assertSame([], $this->gateway->refunds);
    }

    public function test_a_payment_that_came_through_meanwhile_is_refunded(): void
    {
        $registration = $this->registration($this->paidActivity(), 'Bert', 'open');
        $this->gateway->statuses['tr_bert'] = 'paid'; // Mollie knows more than the page did

        $this->cancel($registration, ['refund' => 'full']);

        $this->assertSame([1250], array_column($this->gateway->refunds, 'amount'));
        $this->assertModelMissing($registration);
    }

    public function test_cancelling_a_free_registration_asks_and_mails_without_refund(): void
    {
        $activity = Activity::factory()->create(['title' => 'Gouwdag']);
        $registration = $this->registration($activity, 'Greet');

        $this->actingAs($activity->user)
            ->get("/activiteiten/{$activity->slug}/inschrijvingen")
            ->assertSee('Ben je zeker? Greet krijgt een e-mail dat de inschrijving geannuleerd is.')
            ->assertSee('data-confirm-button="Inschrijving annuleren"', false)
            ->assertDontSee('name="refund"', false);

        $this->cancel($registration)->assertSessionHas('status', 'De inschrijving van Greet is geannuleerd.');

        $this->assertModelMissing($registration);
        $this->assertSame([], $this->gateway->refunds);
        Mail::assertSent(RegistrationCancelled::class, fn ($mail) => $mail->hasTo('greet@example.com') && $mail->refundedCents === 0);
    }

    public function test_an_unpaid_registration_is_removed_without_mail(): void
    {
        $registration = $this->registration($this->paidActivity(), 'Olga', 'canceled');

        $this->cancel($registration);

        $this->assertModelMissing($registration);
        $this->assertSame([], $this->gateway->refunds);
        Mail::assertNothingSent();
    }

    public function test_deleting_a_paid_activity_refunds_everyone_in_full(): void
    {
        $activity = $this->paidActivity();
        $this->registration($activity, 'Bert', 'paid');
        $this->registration($activity, 'Ann', 'paid');
        $this->registration($activity, 'Olga', 'failed');

        $this->actingAs($activity->user)
            ->get("/activiteiten/{$activity->slug}/bewerken")
            ->assertSee('ALLE betaalde inschrijvingen worden volledig terugbetaald.')
            ->assertSee('2 deelnemers krijgen samen')
            ->assertSee('€ 25,00 teruggestort via Mollie, zonder administratiekosten.')
            ->assertSee('Alle 2 deelnemers krijgen een e-mail dat de activiteit niet doorgaat en dat het geld binnen enkele werkdagen teruggestort wordt.')
            ->assertSee('data-confirm-button="Verwijderen en terugbetalen"', false);

        $this->delete("/activiteiten/{$activity->slug}")
            ->assertRedirect('/activiteiten')
            ->assertSessionHas('status', '"Gouwweekend" is verwijderd. 2 deelnemers kregen een annuleringsmail. De terugbetalingen (samen € 25,00) worden verwerkt.');

        $this->assertSame([1250, 1250], array_column($this->gateway->refunds, 'amount'));
        $this->assertModelMissing($activity);
        $this->assertSame(0, Registration::count());
        Mail::assertSent(RegistrationCancelled::class, 2);
        Mail::assertSent(RegistrationCancelled::class, fn ($mail) => $mail->activityCancelled && $mail->refundedCents === 1250 && $mail->adminCostCents === 0);
    }

    public function test_a_failed_refund_keeps_the_activity_with_only_that_registration(): void
    {
        $activity = $this->paidActivity();
        $this->registration($activity, 'Bert', 'paid');
        $ann = $this->registration($activity, 'Ann', 'paid');
        $this->gateway->refusedRefunds[] = 'tr_ann';

        $this->actingAs($activity->user)->delete("/activiteiten/{$activity->slug}")
            ->assertRedirect("/activiteiten/{$activity->slug}/bewerken")
            ->assertSessionHasErrors('cancel');

        $this->assertModelExists($activity);
        $this->assertSame([$ann->id], $activity->registrations()->pluck('id')->all()); // Bert is refunded and gone
        Mail::assertSent(RegistrationCancelled::class, 1);

        // After fixing the balance at Mollie, deleting again finishes the job.
        $this->gateway->refusedRefunds = [];
        $this->delete("/activiteiten/{$activity->slug}")->assertRedirect('/activiteiten');
        $this->assertModelMissing($activity);
    }

    public function test_deleting_a_free_activity_warns_and_mails_everyone(): void
    {
        $activity = Activity::factory()->create(['title' => 'Gouwdag']);
        $this->registration($activity, 'Greet');
        $this->registration($activity, 'Jan');

        $this->actingAs($activity->user)
            ->get("/activiteiten/{$activity->slug}/bewerken")
            ->assertSee('Ben je zeker? Alle 2 deelnemers krijgen een e-mail dat de activiteit niet doorgaat.')
            ->assertDontSee('terugbetaald');

        $this->delete("/activiteiten/{$activity->slug}")
            ->assertSessionHas('status', '"Gouwdag" is verwijderd. 2 deelnemers kregen een annuleringsmail.');

        $this->assertSame([], $this->gateway->refunds);
        Mail::assertSent(RegistrationCancelled::class, fn ($mail) => $mail->activityCancelled && $mail->refundedCents === 0);
        Mail::assertSent(RegistrationCancelled::class, 2);
    }

    public function test_the_cancellation_mails(): void
    {
        $registration = $this->registration($this->paidActivity(), 'Bert', 'paid');

        $refund = (new RegistrationCancelled($registration, 1150, 100))->render();
        $this->assertStringContainsString('Je inschrijving is geannuleerd', $refund);
        $this->assertStringContainsString('€ 11,50', $refund);
        $this->assertStringContainsString('€ 1,00 administratiekosten ingehouden', $refund);
        $this->assertStringContainsString('binnen enkele werkdagen', $refund);
        $this->assertSame('Je inschrijving is geannuleerd: Gouwweekend', (new RegistrationCancelled($registration))->envelope()->subject);

        $free = (new RegistrationCancelled($registration, 0, 0, activityCancelled: true))->render();
        $this->assertStringContainsString('Deze activiteit gaat niet door', $free);
        $this->assertStringNotContainsString('terug', $free);
        $this->assertSame('Gaat niet door: Gouwweekend', (new RegistrationCancelled($registration, activityCancelled: true))->envelope()->subject);
    }
}
