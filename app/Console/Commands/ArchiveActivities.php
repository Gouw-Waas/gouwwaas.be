<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Registration;
use App\Payments\PaymentException;
use App\Payments\RegistrationCheckout;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The nightly clean-up:
 *  1. Archive activities that ended more than gouw.archive_after_months ago: delete them and
 *     their registrations permanently, like an admin's "Archiveren" (no refunds, no e-mails).
 *  2. Ask Mollie again about payments that have been in progress for a day (a missed
 *     webhook would otherwise leave them holding a place forever).
 *  3. Delete abandoned online registrations: unpaid for a day, or their payment failed
 *     (see Registration::scopeAbandoned()).
 *
 * Runs once a day from a cron job in the hosting control panel (see README). It prints
 * nothing when all goes well, so the cron e-mail only arrives when something fails:
 * errors are always printed. What was done goes to the log; -v shows it too.
 */
class ArchiveActivities extends Command
{
    protected $signature = 'activities:archive
        {--dry-run : Only list what would be archived or cleaned up}';

    protected $description = 'Delete activities that ended longer ago than the retention period, and abandoned unpaid registrations';

    private bool $dryRun;

    private bool $chatty;

    public function handle(RegistrationCheckout $checkout): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->chatty = $this->dryRun || $this->output->isVerbose();

        $this->archiveActivities();
        $failed = $this->checkStalePayments($checkout);
        $this->deleteAbandonedRegistrations();

        if ($failed) {
            $this->error("Mollie could not be asked about {$failed} ".($failed === 1 ? 'payment' : 'payments').'; tried again tomorrow (see the log).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function archiveActivities(): void
    {
        $months = (int) config('gouw.archive_after_months');
        $activities = Activity::archivable()->withCount('registrations')->orderBy('starts_at')->get();

        if ($activities->isEmpty()) {
            $this->chat("Nothing to archive (retention: {$months} months after an activity ended).");

            return;
        }

        foreach ($activities as $activity) {
            $this->chat(sprintf(
                '%s "%s" (ended %s, %d registrations)',
                $this->dryRun ? 'Would archive' : 'Archiving',
                $activity->title,
                $activity->endedAt()->format('d/m/Y'),
                $activity->registrations_count,
            ));

            if (! $this->dryRun) {
                $activity->delete(); // registrations go with it (cascade)
            }
        }

        if (! $this->dryRun) {
            Log::info('Archived activities automatically', [
                'activities' => $activities->pluck('title')->all(),
                'registrations' => $activities->sum('registrations_count'),
                'retention_months' => $months,
            ]);
        }

        $this->chat(($this->dryRun ? 'Would archive ' : 'Archived ').$activities->count().' '.($activities->count() === 1 ? 'activity' : 'activities').'.', info: true);
    }

    /**
     * Payments still in progress after a day: fetch their status from Mollie. Ones that
     * ended without being paid are deleted straight away (nobody touched them for a day).
     *
     * @return int how many could not be checked
     */
    private function checkStalePayments(RegistrationCheckout $checkout): int
    {
        $failed = 0;

        foreach (Registration::stalePayment()->with('activity')->get() as $registration) {
            if ($this->dryRun) {
                $this->chat("Would check payment {$registration->mollie_payment_id} of \"{$registration->name}\" ({$registration->activity->title}) with Mollie");

                continue;
            }

            try {
                $checkout->refresh($registration);
            } catch (PaymentException $e) {
                report($e);
                $failed++;

                continue;
            }

            $this->chat("Payment {$registration->mollie_payment_id} of \"{$registration->name}\" ({$registration->activity->title}): {$registration->payment_status}");

            if ($registration->paymentFailed()) {
                $this->deleteRegistrations(new Collection([$registration]));
            }
        }

        return $failed;
    }

    private function deleteAbandonedRegistrations(): void
    {
        $registrations = Registration::abandoned()->with('activity')->orderBy('id')->get();

        if ($registrations->isEmpty()) {
            $this->chat('No abandoned registrations.');

            return;
        }

        $this->deleteRegistrations($registrations);
    }

    /**
     * @param  Collection<int, Registration>  $registrations
     */
    private function deleteRegistrations(Collection $registrations): void
    {
        foreach ($registrations as $registration) {
            $this->chat(sprintf(
                '%s unpaid registration of "%s" (%s, %s)',
                $this->dryRun ? 'Would delete' : 'Deleting',
                $registration->name,
                $registration->activity->title,
                $registration->payment_status ?? 'no payment started',
            ));
        }

        if ($this->dryRun) {
            return;
        }

        // Checked again while deleting: someone who just tried again ("Opnieuw proberen") stays.
        Registration::whereKey($registrations->modelKeys())->unpaidOnline()->delete();

        Log::info('Deleted abandoned registrations', [
            'registrations' => $registrations->map(fn (Registration $registration) => [
                'id' => $registration->id,
                'activity' => $registration->activity->title,
                'payment_status' => $registration->payment_status,
            ])->all(),
        ]);
    }

    private function chat(string $message, bool $info = false): void
    {
        if ($this->chatty) {
            $info ? $this->info($message) : $this->line($message);
        }
    }
}
