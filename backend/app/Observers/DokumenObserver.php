<?php

namespace App\Observers;

use App\Exceptions\BusinessRuleException;
use App\Models\Dokumen;
use App\Models\DokumenVersi;
use App\Models\FileLampiran;
use App\Services\DokumenService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DokumenObserver
{
    public function saving(Model $model): void
    {
        $this->guard($model, false);
    }

    public function deleting(Model $model): void
    {
        $this->guard($model, true);
    }

    private function guard(Model $model, bool $delete): void
    {
        $stored = $model->exists ? $model->newQuery()->whereKey($model->id)->lockForUpdate()->firstOrFail() : null;
        if ($model instanceof DokumenVersi) {
            if ($stored && ($stored->status === 'FINAL' || $stored->dibekukan_pada)) {
                throw new BusinessRuleException('dokumen_final', 'Versi FINAL tidak dapat diubah atau dihapus.');
            }
            if ($stored && ((int) $stored->dokumen_id !== (int) $model->dokumen_id || (int) $stored->nomor_versi !== (int) $model->nomor_versi)) {
                throw new BusinessRuleException('induk_dokumen_tetap', 'Dokumen dan nomor versi tidak dapat dipindahkan.');
            }
            if (! $delete && $model->status === 'FINAL') {
                if (! $stored) {
                    throw new BusinessRuleException('dokumen_belum_siap', 'Buat versi DRAFT sebelum finalisasi.');
                }
                app(DokumenService::class)->assertReady($model);
                $model->dibekukan_pada = now();
            }
        }
        if ($model instanceof Dokumen && $stored && (int) $stored->unit_pemilik_id !== (int) $model->unit_pemilik_id) {
            throw new BusinessRuleException('unit_dokumen_tetap', 'Kepemilikan dokumen tidak dapat dipindahkan.');
        }
        if ($model instanceof FileLampiran && $stored) {
            if ($delete && DB::table('DOKUMEN_VERSI_FILE')->where('file_id', $model->id)->exists()) {
                throw new BusinessRuleException('file_masih_digunakan', 'File masih dirujuk versi dokumen.');
            }
            if (! $delete && (array_diff(array_keys($model->getDirty()), ['status_pemeriksaan', 'updated_at']) || $stored->status_pemeriksaan !== 'PENDING' || ! in_array($model->status_pemeriksaan, ['AMAN', 'DITOLAK'], true))) {
                throw new BusinessRuleException('file_tidak_dapat_diubah', 'Isi dan metadata unggahan tidak dapat ditimpa.');
            }
        }
    }
}
