<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class ArchiveActivitiesTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function activity(string $title, array $attributes): Activity
    {
        return Activity::factory()->create(['title' => $title, 'registration_deadline' => now()->subYears(5)] + $attributes);
    }

    public function test_activities_are_archived_two_years_after_they_ended(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        config(['gouw.archive_after_months' => 24]);

        $old = $this->activity('Oud', ['starts_at' => now()->subMonths(24)->subDays(2), 'ends_at' => now()->subMonths(24)->subMinute()]);
        $recent = $this->activity('Recent', ['starts_at' => now()->subMonths(23), 'ends_at' => now()->subMonths(23)->addHours(3)]);
        // Without an end: over one day after the start, so archived 24 months after start + 1 day.
        $oldNoEnd = $this->activity('Oud zonder einde', ['starts_at' => now()->subMonths(24)->subDay()->subMinute(), 'ends_at' => null]);
        $recentNoEnd = $this->activity('Recent zonder einde', ['starts_at' => now()->subMonths(24)->subHours(12), 'ends_at' => null]);

        $old->registrations()->create(['name' => 'Ann', 'email' => 'ann@example.com', 'answers' => []]);

        $this->artisan('activities:archive', ['--verbose' => true])
            ->expectsOutputToContain('Archiving "Oud"')
            ->expectsOutputToContain('Archiving "Oud zonder einde"')
            ->expectsOutputToContain('Archived 2 activities.')
            ->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelMissing($oldNoEnd);
        $this->assertModelExists($recent);
        $this->assertModelExists($recentNoEnd);
        $this->assertSame(0, $old->registrations()->count());
    }

    /**
     * Run from cron: silent when all goes well, so the cron e-mail only arrives on errors.
     */
    public function test_it_prints_nothing_when_all_goes_well(): void
    {
        $this->artisan('activities:archive')->doesntExpectOutput()->assertSuccessful();

        $old = $this->activity('Oud', ['starts_at' => now()->subYears(3), 'ends_at' => now()->subYears(3)->addHours(3)]);
        $this->artisan('activities:archive')->doesntExpectOutput()->assertSuccessful();
        $this->assertModelMissing($old);
    }

    /**
     * Errors are not swallowed (unlike with --quiet), so cron mails them.
     */
    public function test_a_failure_is_not_silent(): void
    {
        Schema::drop('registrations'); // the query counts registrations, so it fails

        $this->expectException(QueryException::class);
        $this->artisan('activities:archive')->run();
    }

    public function test_archiving_never_refunds_or_mails(): void
    {
        Mail::fake();
        $gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $gateway);

        $old = $this->activity('Oud betaald', ['starts_at' => now()->subYears(3), 'ends_at' => now()->subYears(3)->addHours(3), 'price_cents' => 1000]);
        $paid = $old->registrations()->create(['name' => 'Bert', 'email' => 'bert@example.com', 'answers' => [], 'amount_cents' => 1000]);
        $paid->forceFill(['payment_status' => 'paid', 'mollie_payment_id' => 'tr_bert'])->save();

        $this->artisan('activities:archive')->assertSuccessful();

        $this->assertModelMissing($paid);
        $this->assertSame([], $gateway->refunds);
        Mail::assertNothingSent();
    }

    public function test_dry_run_lists_and_deletes_nothing(): void
    {
        $old = $this->activity('Oud', ['starts_at' => now()->subYears(3), 'ends_at' => now()->subYears(3)->addHours(3)]);

        $this->artisan('activities:archive --dry-run')
            ->expectsOutputToContain('Would archive "Oud"')
            ->expectsOutputToContain('Would archive 1 activity.')
            ->assertSuccessful();

        $this->assertModelExists($old);

        $old->delete();
        $this->artisan('activities:archive --dry-run')->expectsOutputToContain('Nothing to archive (retention: 24 months')->assertSuccessful();
    }

    public function test_the_retention_period_is_shown_where_it_matters(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $past = $this->activity('Vorige maand', ['starts_at' => now()->subMonth(), 'ends_at' => now()->subMonth()->addHours(3)]);

        $this->get('/privacy')->assertSee('Dat gebeurt automatisch 2 jaar na afloop');
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/activiteiten')
            ->assertSee('2 jaar na afloop gebeurt dat automatisch.')
            ->assertSee('Automatisch op '.$past->archivesAt()->translatedFormat('j M Y'));

        config(['gouw.archive_after_months' => 18]);
        $this->assertSame('18 maanden', Activity::retentionLabel());
        $this->get('/privacy')->assertSee('Dat gebeurt automatisch 18 maanden na afloop');
    }
}
