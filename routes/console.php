<?php

use App\Console\Commands\AutoCancelTimedOutRides;
use App\Console\Commands\QueueHealthCheck;
use App\Console\Commands\SendRideReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(AutoCancelTimedOutRides::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

Schedule::command(SendRideReminders::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// Runs via the cron-driven scheduler (not the queue worker itself) so it can
// still detect and log a dead/stopped queue:work process.
Schedule::command(QueueHealthCheck::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
