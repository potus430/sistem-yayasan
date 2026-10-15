<?php

use App\Jobs\SyncDataToCentral;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncDataToCentral)
    ->everyFiveMinutes()
    ->when(fn (): bool => filled(config('sync.central_url')) && filled(config('sync.api_token')))
    ->withoutOverlapping();
