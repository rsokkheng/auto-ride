<?php

use App\Console\Commands\AutoCancelTimedOutRides;
use App\Console\Commands\SendRideReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(AutoCancelTimedOutRides::class)->everyMinute();
Schedule::command(SendRideReminders::class)->everyFifteenMinutes();
