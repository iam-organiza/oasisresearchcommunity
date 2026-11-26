<?php

namespace App\Console\Commands;

use App\Exam\Exam;
use Illuminate\Console\Command;

/**
 * Marks exam attempts whose 60 minutes ran out while the candidate was away,
 * so their result (and any offer or email) does not wait for them to come back.
 */
class CloseExpiredExams extends Command
{
    protected $signature = 'exam:close-expired';

    protected $description = 'Mark exam attempts whose time has run out';

    public function handle(): int
    {
        $this->line('Marked '.Exam::closeExpired().' attempts.');

        return self::SUCCESS;
    }
}
