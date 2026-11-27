<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RevenueTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function register(Activity $activity, string $name, array $attributes = []): Registration
    {
        $registration = $activity->registrations()->create(['name' => $name, 'email' => strtolower($name).'@example.com', 'answers' => [], 'amount_cents' => $activity->price_cents]);
        $registration->forceFill($attributes)->save();

        return $registration;
    }

    /** @return TestResponse<Response> */
    private function overview(Activity $activity): TestResponse
    {
        return $this->actingAs($activity->user)->get("/activiteiten/{$activity->slug}/inschrijvingen");
    }

    public function test_online_and_manual_revenue_and_what_is_still_to_be_received(): void
    {
        $activity = Activity::factory()->create(['price_cents' => 1250]);
        $this->register($activity, 'Online1', ['payment_status' => 'paid', 'mollie_payment_id' => 'tr_1', 'paid_at' => now()]);
        $this->register($activity, 'Online2', ['payment_status' => 'paid', 'mollie_payment_id' => 'tr_2', 'paid_at' => now()]);
        $this->register($activity, 'Cash', ['source' => 'manual', 'payment_status' => 'paid', 'paid_manually' => true, 'paid_at' => now()]);
        $this->register($activity, 'Later', ['source' => 'manual']);
        $this->register($activity, 'Expired', ['payment_status' => 'expired', 'mollie_payment_id' => 'tr_3']); // not counted
        $this->register($activity, 'Busy', ['payment_status' => 'open', 'mollie_payment_id' => 'tr_4']);       // not counted

        $this->assertSame([
            'online' => 2500, 'online_count' => 2,
            'manual' => 1250, 'manual_count' => 1,
            'outstanding' => 1250, 'outstanding_count' => 1,
            'total' => 3750,
        ], $activity->revenue());

        $this->overview($activity)
            ->assertSee('Financieel overzicht')
            ->assertSeeInOrder(['Online betaald', '2 inschrijvingen', '€ 25,00'])
            ->assertSeeInOrder(['Manueel betaald', '1 inschrijving', '€ 12,50'])
            ->assertSeeInOrder(['Totale inkomsten', '€ 37,50'])
            ->assertSeeInOrder(['Nog te ontvangen', '€ 12,50']);
    }

    public function test_without_manual_payments_there_is_no_manual_line(): void
    {
        $activity = Activity::factory()->create(['price_cents' => 1000]);
        $this->register($activity, 'Online', ['payment_status' => 'paid', 'mollie_payment_id' => 'tr_1', 'paid_at' => now()]);

        $this->overview($activity)
            ->assertSeeInOrder(['Online betaald', '€ 10,00', 'Totale inkomsten', '€ 10,00'])
            ->assertDontSee('Manueel betaald')
            ->assertDontSee('Nog te ontvangen');
    }

    public function test_free_activities_have_no_financial_summary(): void
    {
        $activity = Activity::factory()->create(['price_cents' => 0]);
        $this->register($activity, 'Jan');

        $this->overview($activity)->assertOk()->assertDontSee('Financieel overzicht');
    }

    public function test_no_summary_or_payment_column_when_nobody_paid_anything(): void
    {
        // Registered while the activity was still free; it costs money now.
        $activity = Activity::factory()->create(['price_cents' => 600]);
        $this->register($activity, 'Jan', ['amount_cents' => 0]);

        $this->overview($activity)->assertOk()
            ->assertDontSee('Financieel overzicht')
            ->assertDontSee('<th scope="col">Betaling</th>', false);
    }

    public function test_no_summary_without_registrations(): void
    {
        $activity = Activity::factory()->create(['price_cents' => 1000]);

        $this->overview($activity)->assertOk()->assertDontSee('Financieel overzicht');
    }

    public function test_the_price_paid_counts_not_the_current_price(): void
    {
        $activity = Activity::factory()->create(['price_cents' => 1000]);
        $this->register($activity, 'Early', ['payment_status' => 'paid', 'mollie_payment_id' => 'tr_1', 'paid_at' => now()]);
        $activity->update(['price_cents' => 1500]);
        $this->register($activity->fresh() ?? $activity, 'Late', ['payment_status' => 'paid', 'mollie_payment_id' => 'tr_2', 'paid_at' => now()]);

        $this->assertSame(2500, ($activity->fresh() ?? $activity)->revenue()['total']);
    }
}
