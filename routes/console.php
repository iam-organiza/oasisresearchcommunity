<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Declined applications' documents are deleted after their keeping period (see the command).
Schedule::command('membership:prune-documents')->dailyAt('02:30');

// Rejected comments, with the email addresses on them, 90 days after rejection.
Schedule::command('comments:prune-rejected')->dailyAt('02:40');

// The day's dollar rate, for dollar prices; admins hear if it goes stale.
Schedule::command('fees:refresh-rate')->dailyAt('06:15');

// Online payments whose payers closed the page before coming back.
Schedule::command('payments:check-pending')->everyTenMinutes();

// Yearly subscriptions: next year's charge, reminders and lapsing.
Schedule::command('membership:subscriptions')->dailyAt('07:00');

// The monthly update on the 25th (OASIS, 2026-11-27).
Schedule::command('membership:newsletter')->monthlyOn(25, '09:00');

// Exam attempts whose time ran out with the candidate away.
Schedule::command('exam:close-expired')->everyFiveMinutes();
