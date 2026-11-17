<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Registration;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class CapacityTest extends TestCase
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

    private function register(Activity $activity, string $name, ?string $status = null): Registration
    {
        $registration = $activity->registrations()->create(['name' => $name, 'email' => strtolower($name).'@example.com', 'answers' => [], 'amount_cents' => $activity->price_cents]);

        if ($status) {
            $registration->forceFill(['payment_status' => $status, 'mollie_payment_id' => 'tr_'.strtolower($name)])->save();
            $this->gateway->statuses['tr_'.strtolower($name)] = $status;
        }

        return $registration;
    }

    public function test_without_capacity_there_is_no_limit(): void
    {
        $activity = Activity::factory()->create();
        $this->register($activity, 'Jan');

        $this->assertNull($activity->placesLeft());
        $this->assertFalse($activity->isFull());
        $this->assertNull($activity->placesLabel());
    }

    public function test_a_full_activity_takes_no_more_registrations(): void
    {
        $activity = Activity::factory()->create(['capacity' => 2]);
        $this->register($activity, 'Jan');

        $this->get("/activiteiten/{$activity->slug}")->assertSee('Nog 1 plaats');
        $this->get('/activiteiten')->assertSee('Nog 1 plaats');

        $this->register($activity, 'Piet');

        $this->get("/activiteiten/{$activity->slug}")
            ->assertSee('Deze activiteit is volzet')
            ->assertDontSee('name="email"', false);

        $this->post("/activiteiten/{$activity->slug}/inschrijven", ['name' => 'Joris', 'email' => 'joris@example.com'])
            ->assertSessionHasErrors('registration');

        $this->assertSame(2, $activity->registrations()->count());
    }

    public function test_payments_in_progress_hold_a_place_and_failed_ones_do_not(): void
    {
        $activity = Activity::factory()->create(['capacity' => 2, 'price_cents' => 1000]);
        $this->register($activity, 'Paid', 'paid');
        $this->register($activity, 'Busy', 'open');
        $this->register($activity, 'Failed', 'expired');
        $this->register($activity, 'Started'); // filled in the form, never went to Mollie

        $this->assertSame(2, $activity->takenPlaces());
        $this->assertTrue($activity->isFull());

        $this->gateway->statuses['tr_busy'] = 'expired';
        Registration::where('name', 'Busy')->update(['payment_status' => 'expired']);

        $this->assertSame(1, $activity->fresh()?->takenPlaces());
    }

    public function test_paying_is_refused_once_someone_else_took_the_last_place(): void
    {
        $activity = Activity::factory()->create(['capacity' => 1, 'price_cents' => 1000]);
        $late = $this->register($activity, 'Late');
        $this->register($activity, 'First', 'paid');

        $this->get("/inschrijvingen/{$late->token}")->assertSee('intussen volzet');
        $this->post("/inschrijvingen/{$late->token}/betalen")->assertRedirect()->assertSessionHasErrors('payment');

        $this->assertSame([], $this->gateway->created);
    }

    public function test_a_payment_in_progress_can_still_be_finished_when_the_activity_is_full(): void
    {
        $activity = Activity::factory()->create(['capacity' => 1, 'price_cents' => 1000]);
        $busy = $this->register($activity, 'Busy', 'open');

        $this->post("/inschrijvingen/{$busy->token}/betalen")->assertRedirect('https://mollie.test/checkout/tr_busy');
    }

    public function test_capacity_is_validated_on_the_activity_form(): void
    {
        $activity = Activity::factory()->create();

        $this->actingAs($activity->user)
            ->put("/activiteiten/{$activity->slug}", [
                'title' => $activity->title,
                'description' => $activity->description,
                'location' => $activity->location,
                'starts_at' => $activity->starts_at->format('Y-m-d\TH:i'),
                'registration_deadline' => $activity->registration_deadline->format('Y-m-d\TH:i'),
                'capacity' => '0',
            ])
            ->assertSessionHasErrors('capacity');
    }
}
