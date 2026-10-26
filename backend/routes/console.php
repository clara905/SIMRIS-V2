<?php

use App\Exceptions\BusinessRuleException;
use App\Services\SinkronAsetService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    try {
        app(SinkronAsetService::class)->enqueue(null);
    } catch (BusinessRuleException $exception) { /* An existing queued/running sync owns the lock. */
    }
})->dailyAt(config('aset.jadwal'))->timezone('Asia/Jakarta')->when(fn (): bool => (bool) config('aset.enabled'));
