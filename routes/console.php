<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('consent-logs:anonymize-retention')
    ->monthlyOn(1, '04:00')
    ->timezone('Europe/Rome');

Schedule::command('leads:anonymize-stale')
    ->monthlyOn(1, '03:00')
    ->timezone('Europe/Rome');

Schedule::command('editorial:generate-sitemaps')
    ->dailyAt('02:00')
    ->timezone('Europe/Rome');

Schedule::command('editorial:generate-llms-txt')
    ->weeklyOn(1, '02:30')
    ->timezone('Europe/Rome');

Schedule::command('editorial:process-index-queue')
    ->everyFiveMinutes()
    ->timezone('Europe/Rome');

Schedule::command('editorial:purge-view-events')
    ->weeklyOn(1, '03:30')
    ->timezone('Europe/Rome');

Schedule::command('editorial:review-digest')
    ->dailyAt('08:00')
    ->timezone('Europe/Rome');
