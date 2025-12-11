<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');
Schedule::call(function () {
        Log::info('CMD-RUNNER CRON WORKING: ' . now());
    })->everyMinute();
Schedule::command('app:test-automation-command')->everyMinute();
Schedule::command('automation:run')->everyMinute();