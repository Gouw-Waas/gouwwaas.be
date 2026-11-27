<?php

namespace Tests\Feature;

use App\Mail\RegistrationConfirmed;
use App\Models\Activity;
use App\Models\Registration;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class CheckoutTest extends TestCase
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

    /** @param array<string, mixed> $data */
    private function register(Activity $activity, array $data = []): Registration
    {
        $activity->syncFields([['label' => 'Tak', 'type' => 'text', 'required' => false]]);

        $this->post("/activiteiten/{$activity->slug}/inschrijven", $data + [
            'name' => 'Jan Peeters',
            'email' => 'jan@example.com',
            'answers' => [$activity->fields()->first()->id => 'Welpen'],
        ])->assertRedirect();

        return $activity->registrations()->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $state */
    private function paidActivity(array $state = []): Activity
    {
        return Activity::factory()->create(['price_cents' => 1250, 'title' => 'Gouwweekend'] + $state);
    }

    public function test_free_registration_is_confirmed_and_mailed_straight_away(): void
    {
        $activity = Activity::factory()->create(['title' => 'Gouwdag']);

        $registration = $this->register($activity);

        $this->assertTrue($registration->isConfirmed());
        $this->assertNotNull($registration->confirmation_sent_at);
        $this->assertSame([], $this->gateway->created);
        Mail::assertSent(RegistrationConfirmed::class, fn ($mail) => $mail->hasTo('jan@example.com') && $mail->registration->is($registration));

        $this->get("/inschrijvingen/{$registration->token}")->assertOk()
            ->assertSee('Je bent ingeschreven!')
            ->assertSee('We stuurden een bevestiging naar')
            ->assertDontSee('Betalen');
    }

    public function test_paid_registration_goes_to_the_checkout_first(): void
    {
        $activity = $this->paidActivity();

        $this->post("/activiteiten/{$activity->slug}/inschrijven", ['name' => 'Jan', 'email' => 'jan@example.com'])
            ->assertRedirect(route('inschrijvingen.show', Registration::firstOrFail()));

        $registration = Registration::firstOrFail();
        $this->assertFalse($registration->isConfirmed());
        $this->assertSame(1250, $registration->amount_cents);
        Mail::assertNothingSent();

        $this->get("/inschrijvingen/{$registration->token}")->assertOk()
            ->assertSee('Nog één stap')
            ->assertSee('Betalen · € 12,50')
            ->assertSee('€ 12,50');
    }

    public function test_the_pay_page_lets_its_form_redirect_through_mollie_to_the_bank(): void
    {
        $registration = $this->register($this->paidActivity());

        // Browsers check each redirect of the pay form (Mollie → bank/PayPal) against form-action.
        $csp = $this->get("/inschrijvingen/{$registration->token}")->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("form-action 'self' https:", $csp);
    }

    public function test_paying_sends_the_payer_to_mollie(): void
    {
        $registration = $this->register($this->paidActivity());

        $this->post("/inschrijvingen/{$registration->token}/betalen")
            ->assertRedirect('https://mollie.test/checkout/tr_test1');

        $payment = $this->gateway->created[0];
        $this->assertSame(1250, $payment['amount']);
        $this->assertSame('Inschrijving Gouwweekend – Jan Peeters', $payment['description']);
        $this->assertSame(route('inschrijvingen.show', $registration), $payment['redirectUrl']);
        $this->assertNull($payment['webhookUrl']); // APP_URL is local: Mollie can't call back
        $this->assertSame(['tr_test1', 'open'], [$registration->fresh()->mollie_payment_id, $registration->fresh()->payment_status]);
    }

    public function test_the_webhook_is_used_on_a_public_address(): void
    {
        config(['app.url' => 'https://gouwwaas.be']);
        $registration = $this->register($this->paidActivity());

        $this->post("/inschrijvingen/{$registration->token}/betalen");

        $this->assertSame(route('mollie.webhook'), $this->gateway->created[0]['webhookUrl']);
    }

    public function test_back_from_mollie_with_a_successful_payment(): void
    {
        $registration = $this->register($this->paidActivity());
        $this->post("/inschrijvingen/{$registration->token}/betalen");
        $this->gateway->statuses['tr_test1'] = 'paid';

        $this->get("/inschrijvingen/{$registration->token}")->assertOk()
            ->assertSee('Je bent ingeschreven!')
            ->assertSee('Je betaling van € 12,50 is gelukt.');

        $registration->refresh();
        $this->assertTrue($registration->isConfirmed());
        $this->assertNotNull($registration->paid_at);

        // Reloading the page (or the webhook arriving too) doesn't send a second e-mail.
        $this->get("/inschrijvingen/{$registration->token}")->assertOk();
        $this->post('/mollie/webhook', ['id' => 'tr_test1'])->assertOk();
        Mail::assertSent(RegistrationConfirmed::class, 1);
    }

    public function test_back_from_mollie_with_a_failed_canceled_or_expired_payment(): void
    {
        foreach (['failed' => 'mislukt', 'canceled' => 'geannuleerd', 'expired' => 'verlopen'] as $status => $word) {
            $registration = $this->register($this->paidActivity());
            $this->post("/inschrijvingen/{$registration->token}/betalen");
            $this->gateway->statuses[$registration->fresh()->mollie_payment_id] = $status;

            $this->get("/inschrijvingen/{$registration->token}")->assertOk()
                ->assertSee("De betaling is {$word}")
                ->assertSee('niet</strong> ingeschreven', false)
                ->assertSee('Opnieuw proberen');

            $this->assertFalse($registration->fresh()->isConfirmed());
        }

        Mail::assertNothingSent();
    }

    public function test_trying_again_after_a_failed_payment_starts_a_new_payment(): void
    {
        $registration = $this->register($this->paidActivity());
        $this->post("/inschrijvingen/{$registration->token}/betalen");
        $this->gateway->statuses['tr_test1'] = 'canceled';

        $this->post("/inschrijvingen/{$registration->token}/betalen")->assertRedirect('https://mollie.test/checkout/tr_test2');
        $this->assertSame('tr_test2', $registration->fresh()->mollie_payment_id);
    }

    public function test_an_open_payment_is_continued_instead_of_starting_a_second_one(): void
    {
        $registration = $this->register($this->paidActivity());
        $this->post("/inschrijvingen/{$registration->token}/betalen");

        $this->get("/inschrijvingen/{$registration->token}")->assertSee('Je betaling is nog niet afgerond')->assertSee('Verder betalen');
        $this->post("/inschrijvingen/{$registration->token}/betalen")->assertRedirect('https://mollie.test/checkout/tr_test1');

        $this->assertCount(1, $this->gateway->created);
    }

    public function test_a_payment_still_at_the_bank_is_not_started_again(): void
    {
        $registration = $this->register($this->paidActivity());
        $this->post("/inschrijvingen/{$registration->token}/betalen");
        $this->gateway->statuses['tr_test1'] = 'pending';

        $this->get("/inschrijvingen/{$registration->token}")->assertSee('Je betaling wordt verwerkt')->assertDontSee('Opnieuw proberen');
        $this->post("/inschrijvingen/{$registration->token}/betalen")->assertRedirect(route('inschrijvingen.show', $registration));
        $this->assertCount(1, $this->gateway->created);
    }

    public function test_the_webhook_confirms_and_mails_when_the_payer_never_comes_back(): void
    {
        $registration = $this->register($this->paidActivity());
        $this->post("/inschrijvingen/{$registration->token}/betalen");
        $this->gateway->statuses['tr_test1'] = 'paid';

        $this->post('/mollie/webhook', ['id' => 'tr_test1'])->assertOk();

        $this->assertTrue($registration->fresh()->isConfirmed());
        Mail::assertSent(RegistrationConfirmed::class, 1);
    }

    public function test_the_webhook_ignores_unknown_payments_and_asks_mollie_for_the_status(): void
    {
        $this->post('/mollie/webhook', ['id' => 'tr_onbekend'])->assertOk();
        $this->post('/mollie/webhook')->assertOk();

        // A forged "status" in the request is ignored: Mollie still says open.
        $registration = $this->register($this->paidActivity());
        $this->post("/inschrijvingen/{$registration->token}/betalen");
        $this->post('/mollie/webhook', ['id' => 'tr_test1', 'status' => 'paid'])->assertOk();
        $this->assertFalse($registration->fresh()->isConfirmed());
    }

    public function test_mollie_being_unreachable_is_handled(): void
    {
        $registration = $this->register($this->paidActivity());
        $this->gateway->failing = true;

        $this->post("/inschrijvingen/{$registration->token}/betalen")
            ->assertRedirect(route('inschrijvingen.show', $registration))
            ->assertSessionHasErrors('payment');

        $registration->forceFill(['mollie_payment_id' => 'tr_x', 'payment_status' => 'open'])->save();
        $this->get("/inschrijvingen/{$registration->token}")->assertOk()->assertSee('We konden de status van je betaling nu niet ophalen');
        $this->post('/mollie/webhook', ['id' => 'tr_x'])->assertStatus(500); // Mollie retries later
    }

    public function test_paying_is_refused_after_the_deadline(): void
    {
        $activity = $this->paidActivity();
        $registration = $this->register($activity);
        $activity->update(['registration_deadline' => now()->subMinute()]);

        $this->post("/inschrijvingen/{$registration->token}/betalen")->assertSessionHasErrors('payment');
        $this->assertSame([], $this->gateway->created);
        $this->get("/inschrijvingen/{$registration->token}")->assertSee('De inschrijvingen voor deze activiteit zijn afgesloten.')->assertDontSee('Betalen · €');
    }

    public function test_the_registration_page_needs_the_secret_token(): void
    {
        $registration = $this->register($this->paidActivity());

        $this->get("/inschrijvingen/{$registration->id}")->assertNotFound();
        $this->assertSame(40, strlen($registration->token));
    }

    public function test_a_later_price_change_does_not_change_what_was_agreed(): void
    {
        $activity = $this->paidActivity();
        $registration = $this->register($activity);
        $activity->update(['price_cents' => 2000]);

        $this->post("/inschrijvingen/{$registration->token}/betalen");
        $this->assertSame(1250, $this->gateway->created[0]['amount']);
    }

    public function test_organisers_see_payment_status_and_only_count_confirmed_registrations(): void
    {
        $activity = $this->paidActivity();
        $paid = $this->register($activity, ['name' => 'Betaalde Bert']);
        $this->post("/inschrijvingen/{$paid->token}/betalen");
        $this->gateway->statuses['tr_test1'] = 'paid';
        $this->get("/inschrijvingen/{$paid->token}");
        $this->register($activity, ['name' => 'Open Olga']);

        $this->actingAs($activity->user);
        $this->get("/activiteiten/{$activity->slug}")->assertSee('Inschrijvingen (1)');
        $this->get("/activiteiten/{$activity->slug}/inschrijvingen")->assertOk()
            ->assertSee('1 inschrijving')
            ->assertSee('+ 1 niet betaald')
            ->assertSeeInOrder(['Open Olga', 'Niet betaald'])
            ->assertSeeInOrder(['Betaalde Bert', 'Betaald']);

        $rows = $this->readXlsx($this->get("/activiteiten/{$activity->slug}/inschrijvingen.xlsx")->streamedContent());
        $bert = collect($rows)->first(fn (array $row) => $row[1] === 'Betaalde Bert');
        $olga = collect($rows)->first(fn (array $row) => $row[1] === 'Open Olga');
        $this->assertSame(['Betaald', 12.5], [$bert[3], $bert[4]]); // amount as a number, formatted as euro
        $this->assertSame('Niet betaald', $olga[3]);
    }

    public function test_the_confirmation_mail(): void
    {
        $activity = $this->paidActivity(['location' => 'Gouwhuis Karree']);
        $registration = $this->register($activity);
        $registration->forceFill(['payment_status' => 'paid'])->save();

        $html = (new RegistrationConfirmed($registration))->render();

        $this->assertStringContainsString('Je bent ingeschreven!', $html);
        $this->assertStringContainsString('Gouwweekend', $html);
        $this->assertStringContainsString('Gouwhuis Karree', $html);
        $this->assertStringContainsString('€ 12,50', $html);
        $this->assertStringContainsString('Tak: Welpen', $html);
        // No self-service cancelling: the mail says where to ask.
        $this->assertStringContainsString('Je kan je inschrijving niet zelf annuleren', $html);
        $this->assertStringContainsString('mailto:'.config('mail.from.address'), $html);
        $this->assertSame('Je bent ingeschreven: Gouwweekend', (new RegistrationConfirmed($registration))->envelope()->subject);
    }

    public function test_a_mail_failure_does_not_break_the_registration_and_can_be_retried(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $activity = Activity::factory()->create();
        $this->post("/activiteiten/{$activity->slug}/inschrijven", ['name' => 'Jan', 'email' => 'jan@example.com'])->assertRedirect();

        $registration = Registration::firstOrFail();
        $this->assertTrue($registration->isConfirmed());
        $this->assertNull($registration->confirmation_sent_at); // released, so a later attempt can send it
    }
}
