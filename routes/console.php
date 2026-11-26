<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Declined applications' documents are deleted after their keeping period (see the command).
Schedule::command('membership:prune-documents')->dailyAt('02:30');

// The day's dollar rate, for dollar prices; admins hear if it goes stale.
Schedule::command('fees:refresh-rate')->dailyAt('06:15');

// Online payments whose payers closed the page before coming back.
Schedule::command('payments:check-pending')->everyTenMinutes();
