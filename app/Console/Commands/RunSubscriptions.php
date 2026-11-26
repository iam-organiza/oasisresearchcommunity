<?php

namespace App\Console\Commands;

use App\Account\Subscriptions;
use App\Models\Membership;
use Illuminate\Console\Command;

/**
 * The daily subscriptions round: next year's subscription when it begins,
 * the due and last reminders, and lapsing after 30 days' grace.
 */
class RunSubscriptions extends Command
{
    protected $signature = 'membership:subscriptions';

    protected $description = 'Add yearly subscriptions, send reminders and lapse unpaid memberships';

    public function handle(): int
    {
        Membership::query()->whereNotNull('activated_at')->with('user')->orderBy('id')
            ->each(fn (Membership $membership) => Subscriptions::run($membership));

        return self::SUCCESS;
    }
}
