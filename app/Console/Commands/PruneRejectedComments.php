<?php

namespace App\Console\Commands;

use App\Enums\CommentStatus;
use App\Models\PostComment;
use Illuminate\Console\Command;

/**
 * Deletes rejected Knowledge Base comments, with the names and email
 * addresses on them, 90 days after they were rejected. A rejected comment
 * is never shown, so there is no reason to keep someone's email for it.
 * Replies still waiting under it go with it. Runs nightly from the scheduler.
 */
class PruneRejectedComments extends Command
{
    public const KEEP_DAYS = 90;

    protected $signature = 'comments:prune-rejected {--dry-run : Count what would be deleted without deleting it}';

    protected $description = 'Delete rejected comments 90 days after they were rejected';

    public function handle(): int
    {
        // The status change is the last thing to touch a rejected comment, so updated_at is when it was rejected.
        $due = PostComment::query()
            ->where('status', CommentStatus::Rejected)
            ->where('updated_at', '<=', now()->subDays(self::KEEP_DAYS));

        $count = $due->count();

        if (! $this->option('dry-run')) {
            $due->delete();
        }

        $this->info(($this->option('dry-run') ? 'Would delete' : 'Deleted')." {$count} rejected comment(s).");

        return self::SUCCESS;
    }
}
