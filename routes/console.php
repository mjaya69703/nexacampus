<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('financial:run-invoice-schedules')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('financial:refresh-overdue')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('financial:evaluate-holds')->dailyAt('00:20')->withoutOverlapping();
Schedule::command('academic:send-assignment-reminders')->dailyAt('07:00')->withoutOverlapping();
