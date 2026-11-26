<?php

namespace App\Console\Commands;

use App\Account\OnlineCheckout;
use App\Models\OnlinePayment;
use Illuminate\Console\Command;

/**
 * Checks online payments still waiting for an answer: a member may pay and
 * close the page before coming back to the site. Paid ones are recorded;
 * ones never finished within a day are marked abandoned.
 */
class CheckPendingPayments extends Command
{
    protected $signature = 'payments:check-pending';

    protected $description = 'Check online payments that are still pending with Paystack';

    public function handle(OnlineCheckout $checkout): int
    {
        $pending = OnlinePayment::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhere('checked_at', '<=', now()->subMinutes(10)))
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($pending as $attempt) {
            $status = $checkout->confirm($attempt)->status;
            $this->line("{$attempt->reference}: {$status}");
        }

        return self::SUCCESS;
    }
}
