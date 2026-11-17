<?php

namespace Tests\Feature;

use App\Mail\RegistrationCancelled;
use App\Mail\RegistrationConfirmed;
use App\Models\Activity;
use App\Models\Registration;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class ManualRegistrationTest extends TestCase
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

    private function paidActivity(): Activity
    {
        $activity = Activity::factory()->create(['price_cents' => 1500, 'capacity' => 1]);
        $activity->syncFields([['label' => 'Groep', 'type' => 'text', 'required' => true]]);

        return $activity->load('fields');
    }

    public function test_only_the_organiser_or_an_admin_can_add_someone(): void
    {
        $activity = $this->paidActivity();
        $url = "/activiteiten/{$activity->slug}/inschrijvingen";

        $this->get("{$url}/nieuw")->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get("{$url}/nieuw")->assertForbidden();
        $this->actingAs(User::factory()->create())->post($url, ['name' => 'X', 'email' => 'x@example.com', 'paid' => '1'])->assertForbidden();

        $this->actingAs($activity->user)->get("{$url}/nieuw")->assertOk()->assertSee('Inschrijving toevoegen');
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get("{$url}/nieuw")->assertOk();

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_organiser_adds_someone_who_paid_cash_with_a_confirmation(): void
    {
        $activity = $this->paidActivity();

        $this->actingAs($activity->user)
            ->post("/activiteiten/{$activity->slug}/inschrijvingen", [
                'name' => 'Mieke Janssens',
                'email' => 'mieke@example.com',
                'paid' => '1',
                'send_confirmation' => '1',
                // The required question may stay empty: the organiser may not know.
                'answers' => [$activity->fields[0]->id => ''],
            ])
            ->assertRedirect("/activiteiten/{$activity->slug}/inschrijvingen")
            ->assertSessionHas('status');

        $registration = Registration::sole();
        $this->assertTrue($registration->isManual());
        $this->assertTrue($registration->isConfirmed());
        $this->assertTrue($registration->paid_manually);
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame(1500, $registration->amount_cents);
        $this->assertSame('Betaald (manueel)', $registration->paymentLabel());
        $this->assertFalse($registration->paidOnline());

        Mail::assertSent(RegistrationConfirmed::class, fn ($mail) => $mail->hasTo('mieke@example.com'));
        $this->assertSame(1, $activity->takenPlaces());
    }

    public function test_someone_who_pays_later_is_a_participant_and_can_be_marked_paid(): void
    {
        $activity = $this->paidActivity();

        $this->actingAs($activity->user)
            ->post("/activiteiten/{$activity->slug}/inschrijvingen", [
                'name' => 'Tom',
                'email' => 'tom@example.com',
                'paid' => '0',
                'send_confirmation' => '0',
            ])
            ->assertRedirect();

        $registration = Registration::sole();
        $this->assertTrue($registration->isConfirmed());
        $this->assertFalse($registration->isPaid());
        $this->assertSame('Niet betaald (manueel)', $registration->paymentLabel());
        Mail::assertNothingSent();

        $this->actingAs($activity->user)
            ->get("/activiteiten/{$activity->slug}/inschrijvingen")
            ->assertSee('waarvan 1 nog te betalen')
            ->assertSee('1 / 1')
            ->assertSee('Tom op betaald zetten?');

        $this->actingAs($activity->user)
            ->post("/activiteiten/{$activity->slug}/inschrijvingen/{$registration->id}/betaald", ['send_confirmation' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $registration->refresh();
        $this->assertTrue($registration->paid_manually);
        $this->assertNotNull($registration->paid_at);
        Mail::assertSent(RegistrationConfirmed::class, 1);
    }

    public function test_paid_activities_need_a_payment_choice(): void
    {
        $activity = $this->paidActivity();

        $this->actingAs($activity->user)
            ->post("/activiteiten/{$activity->slug}/inschrijvingen", ['name' => 'Tom', 'email' => 'tom@example.com'])
            ->assertSessionHasErrors('paid');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_organisers_can_go_over_the_maximum(): void
    {
        $activity = $this->paidActivity(); // 1 place
        $activity->registrations()->create(['name' => 'Eerste', 'email' => 'eerste@example.com', 'answers' => [], 'amount_cents' => 0]);

        $this->actingAs($activity->user)->get("/activiteiten/{$activity->slug}/inschrijvingen/nieuw")->assertSee('boven het maximum');

        $this->actingAs($activity->user)
            ->post("/activiteiten/{$activity->slug}/inschrijvingen", ['name' => 'Extra', 'email' => 'extra@example.com', 'paid' => '1'])
            ->assertRedirect();

        $this->assertSame(2, $activity->takenPlaces());
    }

    public function test_an_online_registration_whose_payment_failed_can_be_marked_paid(): void
    {
        $activity = $this->paidActivity();
        $registration = $activity->registrations()->create(['name' => 'Jan', 'email' => 'jan@example.com', 'answers' => [], 'amount_cents' => 1500]);
        $registration->forceFill(['mollie_payment_id' => 'tr_jan', 'payment_status' => 'expired'])->save();
        $this->gateway->statuses['tr_jan'] = 'expired';

        $this->actingAs($activity->user)
            ->post("/activiteiten/{$activity->slug}/inschrijvingen/{$registration->id}/betaald", ['send_confirmation' => '1'])
            ->assertSessionHas('status');

        $registration->refresh();
        $this->assertTrue($registration->isConfirmed());
        $this->assertFalse($registration->paidOnline()); // never refunded through Mollie
        Mail::assertSent(RegistrationConfirmed::class);
    }

    public function test_a_payment_still_running_at_mollie_cannot_be_marked_paid(): void
    {
        $activity = $this->paidActivity();
        $registration = $activity->registrations()->create(['name' => 'Jan', 'email' => 'jan@example.com', 'answers' => [], 'amount_cents' => 1500]);
        $registration->forceFill(['mollie_payment_id' => 'tr_jan', 'payment_status' => 'open'])->save();
        $this->gateway->statuses['tr_jan'] = 'open';

        $this->actingAs($activity->user)
            ->from("/activiteiten/{$activity->slug}/inschrijvingen")
            ->post("/activiteiten/{$activity->slug}/inschrijvingen/{$registration->id}/betaald")
            ->assertSessionHasErrors('cancel');

        $this->assertFalse($registration->fresh()?->paid_manually);
    }

    public function test_others_cannot_mark_a_registration_paid(): void
    {
        $activity = $this->paidActivity();
        $registration = $activity->registrations()->create(['name' => 'Jan', 'email' => 'jan@example.com', 'answers' => [], 'amount_cents' => 1500]);

        $this->actingAs(User::factory()->create())
            ->post("/activiteiten/{$activity->slug}/inschrijvingen/{$registration->id}/betaald")
            ->assertForbidden();

        $this->assertNull($registration->fresh()?->payment_status);
    }

    public function test_cancelling_a_manual_payment_refunds_nothing_through_mollie(): void
    {
        $activity = $this->paidActivity();

        $this->actingAs($activity->user)->post("/activiteiten/{$activity->slug}/inschrijvingen", ['name' => 'Mieke', 'email' => 'mieke@example.com', 'paid' => '1']);
        $registration = Registration::sole();

        $this->actingAs($activity->user)
            ->get("/activiteiten/{$activity->slug}/inschrijvingen")
            ->assertSee('buiten de website om');

        $this->actingAs($activity->user)
            ->delete("/activiteiten/{$activity->slug}/inschrijvingen/{$registration->id}")
            ->assertSessionHas('status');

        $this->assertSame([], $this->gateway->refunds);
        $this->assertDatabaseCount('registrations', 0);
        Mail::assertSent(RegistrationCancelled::class, fn (RegistrationCancelled $mail) => $mail->refundedCents === 0);
    }
}
