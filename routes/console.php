<?php

use App\Services\BonusProgramService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$bonusExpiryCheckTime = BonusProgramService::DEFAULT_EXPIRY_CHECK_TIME;

if (Schema::hasTable('settings')) {
    $bonusExpiryCheckTime = app(BonusProgramService::class)->expiryCheckTimeForSchedule();
}

Schedule::command('bonuses:expire')->dailyAt($bonusExpiryCheckTime);
