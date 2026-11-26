<?php

namespace App\Console\Commands;

use App\Enums\Membership\ApplicationStatus;
use App\Models\MembershipApplication;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes the uploaded documents (CVs, certificates, other evidence) of
 * declined applications once they have been kept long enough: 6 months
 * after the decision, or 2 years when the applicant asked us to keep them
 * in case they reapply. This follows the NDPA 2023 GAID default of ending
 * storage within 6 months once the purpose is done, unless the person
 * consents to longer. Runs nightly from the scheduler.
 */
class PruneApplicationDocuments extends Command
{
    protected $signature = 'membership:prune-documents {--dry-run : List what would be deleted without deleting it}';

    protected $description = 'Delete the documents of declined applications after their keeping period';

    public function handle(): int
    {
        $due = MembershipApplication::query()
            ->where('status', ApplicationStatus::Declined->value)
            ->whereHas('documents')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $short) => $short->where('keep_documents_two_years', false)->where('decided_at', '<=', now()->subMonths(6)))
                ->orWhere(fn (Builder $long) => $long->where('keep_documents_two_years', true)->where('decided_at', '<=', now()->subYears(2))))
            ->with('documents')
            ->get();

        $files = 0;

        foreach ($due as $application) {
            $count = $application->documents->count();
            $files += $count;

            if ($this->option('dry-run')) {
                $this->line("Application {$application->id}: {$count} file(s) due");

                continue;
            }

            // Deleting each model also deletes its file from the private disk.
            $application->documents->each->delete();
            $application->log('documents_deleted', null, null, "{$count} file(s) deleted after the keeping period.");
        }

        $this->info(($this->option('dry-run') ? 'Would delete' : 'Deleted')." {$files} file(s) from {$due->count()} declined application(s).");

        return self::SUCCESS;
    }
}
