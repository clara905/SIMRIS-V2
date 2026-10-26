<?php

namespace App\Jobs;

use App\Contracts\PemindaiBerkas;
use App\Models\FileLampiran;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class PeriksaFileLampiran implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $fileId) {}

    public function handle(PemindaiBerkas $scanner): void
    {
        DB::transaction(function () use ($scanner): void {
            $file = FileLampiran::whereKey($this->fileId)->lockForUpdate()->firstOrFail();
            if ($file->status_pemeriksaan !== 'PENDING') {
                return;
            }
            $file->update(['status_pemeriksaan' => $scanner->aman($file) ? 'AMAN' : 'DITOLAK']);
        });
    }
}
