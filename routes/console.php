<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Declined applications' documents are deleted after their keeping period (see the command).
Schedule::command('membership:prune-documents')->dailyAt('02:30');
