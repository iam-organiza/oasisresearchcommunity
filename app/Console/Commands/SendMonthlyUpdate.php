<?php

namespace App\Console\Commands;

use App\Membership\MembershipMail;
use App\Models\Post;
use App\Models\User;
use App\Notifications\Account\MonthlyUpdate;
use Illuminate\Console\Command;

/**
 * The monthly update on the 25th, to every member who has not stopped it.
 */
class SendMonthlyUpdate extends Command
{
    protected $signature = 'membership:newsletter';

    protected $description = 'Send the monthly update to members';

    public function handle(): int
    {
        $posts = Post::query()->live()
            ->where(fn ($query) => $query->where('published_at', '>=', now()->subMonth())
                ->orWhere(fn ($fallback) => $fallback->whereNull('published_at')->where('created_at', '>=', now()->subMonth())))
            ->newest()
            ->limit(5)
            ->get(['id', 'title', 'slug', 'excerpt', 'published_at', 'created_at']);

        $sent = 0;
        User::query()
            ->whereHas('membership')
            ->whereNull('newsletter_opted_out_at')
            ->whereNotNull('email_verified_at')
            ->with('membership')
            ->orderBy('id')
            ->each(function (User $user) use ($posts, &$sent) {
                MembershipMail::send($user, new MonthlyUpdate($posts));
                $sent++;
            });

        $this->line("Sent to {$sent} members.");

        return self::SUCCESS;
    }
}
