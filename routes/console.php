<?php

use App\Support\LocalTime;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$nigeria = LocalTime::tz();

Schedule::command('recordings:prune')->dailyAt('04:15')->timezone($nigeria);
Schedule::command('events:end-idle')->everyFiveMinutes();
Schedule::command('recordings:assemble-events')->everyFiveMinutes()->withoutOverlapping(180);
Schedule::command('billing:notify-free-period')->dailyAt('09:00')->timezone($nigeria);
Schedule::command('billing:send-invoice-reminders')->dailyAt('09:30')->timezone($nigeria);
Schedule::command('billing:confirm-payments')->everyMinute();
Schedule::command('affiliates:process-holds')->hourly();
