<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cdr:import all --stop-on-failure')
    ->everyFifteenMinutes()
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/cdr-import.log'));

Schedule::command('cdr:cleanup --days=30')
    ->dailyAt('02:30')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/cdr-cleanup.log'));
