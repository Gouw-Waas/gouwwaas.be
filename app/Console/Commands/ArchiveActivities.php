<?php

namespace App\Console\Commands;

use App\Models\Activity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Archive activities that ended more than gouw.archive_after_months ago: delete them and
 * their registrations permanently, like an admin's "Archiveren" (no refunds, no e-mails).
 *
 * Runs once a day from a cron job in the hosting control panel (see README). It prints
 * nothing when all goes well, so the cron e-mail only arrives when something fails:
 * errors are always printed. What was archived goes to the log; -v shows it too.
 */
class ArchiveActivities extends Command
{
    protected $signature = 'activities:archive
        {--dry-run : Only list what would be archived}';

    protected $description = 'Delete activities (and their registrations) that ended longer ago than the retention period';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chatty = $dryRun || $this->output->isVerbose();
        $months = (int) config('gouw.archive_after_months');
        $activities = Activity::archivable()->withCount('registrations')->orderBy('starts_at')->get();

        if ($activities->isEmpty()) {
            if ($chatty) {
                $this->info("Nothing to archive (retention: {$months} months after an activity ended).");
            }

            return self::SUCCESS;
        }

        foreach ($activities as $activity) {
            if ($chatty) {
                $this->line(sprintf(
                    '%s "%s" (ended %s, %d registrations)',
                    $dryRun ? 'Would archive' : 'Archiving',
                    $activity->title,
                    $activity->endedAt()->format('d/m/Y'),
                    $activity->registrations_count,
                ));
            }

            if (! $dryRun) {
                $activity->delete(); // registrations go with it (cascade)
            }
        }

        if (! $dryRun) {
            Log::info('Archived activities automatically', [
                'activities' => $activities->pluck('title')->all(),
                'registrations' => $activities->sum('registrations_count'),
                'retention_months' => $months,
            ]);
        }

        if ($chatty) {
            $this->info(($dryRun ? 'Would archive ' : 'Archived ').$activities->count().' '.($activities->count() === 1 ? 'activity' : 'activities').'.');
        }

        return self::SUCCESS;
    }
}
