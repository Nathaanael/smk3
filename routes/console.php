<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// checks every minute; the saved schedule decides whether anything goes out
Schedule::command('reminders:send-scheduled')->everyMinute()->withoutOverlapping();
