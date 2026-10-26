<?php

namespace App\Observers;

use App\Exceptions\BusinessRuleException;
use App\Models\DokumenVersi;
use App\Models\Soa;
use App\Models\SoaReview;
use App\Models\SoaVersi;
use App\Models\Sop;
use App\Models\SopReview;
use App\Models\SopVersi;
use App\Services\DokumenService;
use App\Services\KepatuhanService;
use Illuminate\Database\Eloquent\Model;

class KepatuhanObserver
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
        if ($model instanceof SopReview || $model instanceof SoaReview) {
            if ($stored) {
                throw new BusinessRuleException('review_tetap', 'Riwayat review tidak dapat diubah atau dihapus.');
            }

return;
        }
        $service = app(KepatuhanService::class);
        if ($stored) {
            foreach (['unit_id', 'unit_pemilik_id', 'sop_id', 'soa_id', 'soa_versi_id', 'soa_detail_id', 'dibuat_oleh', 'nomor_versi'] as $field) {
                if ($stored->getAttribute($field) !== $model->getAttribute($field)) {
                    throw new BusinessRuleException('induk_versi_tetap', 'Induk, identitas penyusun dan nomor versi tidak dapat dipindahkan.');
                }
            }
            if ($model instanceof SopVersi || $model instanceof SoaVersi) {
                if (in_array($stored->status, ['DISAHKAN', 'DITOLAK'], true) || (! $model->workflowMutation && ($stored->status !== 'DRAFT' || $model->isDirty(['status', 'dibekukan_pada'])))) {
                    throw new BusinessRuleException('versi_bukan_draft', 'Versi tidak dapat diubah langsung setelah diajukan.');
                }
            } else {
                $service->draft($model);
            }
            if ($model instanceof Sop || $model instanceof Soa) {
                $class = $model instanceof Sop ? SopVersi::class : SoaVersi::class;
                $field = $model instanceof Sop ? 'sop_id' : 'soa_id';
                if (($class::where($field, $model->id)->where('status', '!=', 'DRAFT')->exists() || ($model instanceof Soa && $model->isDirty(['standar_id', 'periode_id']) && $class::where($field, $model->id)->exists()))) {
                    throw new BusinessRuleException('induk_dibekukan', 'Induk memiliki versi diajukan; buat versi baru untuk perubahan isi.');
                }
            }
        } elseif ($model instanceof SopVersi || $model instanceof SoaVersi) {
            if ($model->status !== 'DRAFT') {
                throw new BusinessRuleException('versi_harus_draft', 'Versi baru harus DRAFT.');
            }
        } else {
            $service->draft($model);
        }
        if (! $delete && $model instanceof SopVersi) {
            $document = DokumenVersi::findOrFail($model->dokumen_versi_id);
            if ($document->status !== 'FINAL') {
                throw new BusinessRuleException('dokumen_belum_final', 'SOP harus memakai versi dokumen FINAL.');
            } app(DokumenService::class)->assertReady($document);
        }
    }
}
