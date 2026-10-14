<?php

namespace App\Jobs;

use App\Contracts\AsetSyncDriver;
use App\Services\SinkronAsetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SinkronAset implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $runId, public string $owner) {}

    public function handle(SinkronAsetService $service, AsetSyncDriver $driver): void
    {
        $service->run($this->runId, $this->owner, $driver);
    }

    public function failed(?\Throwable $exception): void
    {
        \App\Models\SinkronAset::find($this->runId)?->update(['status' => 'GAGAL', 'galat' => 'Job sinkronisasi gagal atau melewati batas waktu.', 'selesai_pada' => now()]);
        Cache::restoreLock(SinkronAsetService::LOCK, $this->owner)->release();
    }
}
