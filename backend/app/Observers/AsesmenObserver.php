<?php

namespace App\Observers;

use App\Exceptions\BusinessRuleException;
use App\Models\AsesmenAset;
use App\Models\AsesmenRisiko;
use App\Models\MatriksRisiko;
use App\Models\PenilaianRisiko;
use App\Models\Risiko;
use App\Models\UsulanKeputusan;
use App\Services\MetodeRisikoService;
use Illuminate\Database\Eloquent\Model;

class AsesmenObserver
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
        if ($model instanceof AsesmenRisiko) {
            if (! $stored) {
                app(MetodeRisikoService::class)->forAssessment((int) $model->versi_metode_id);

                return;
            }
            if ($stored->dibekukan_pada) {
                throw new BusinessRuleException('asesmen_dibekukan', 'Asesmen sudah dibekukan.');
            }
            foreach (['seri_asesmen_id', 'unit_id', 'periode_id', 'versi_metode_id', 'dibuat_oleh', 'nomor_versi'] as $field) {
                if ($model->isDirty($field)) {
                    throw new BusinessRuleException('induk_asesmen_tetap', 'Identitas dan metode asesmen tidak dapat dipindahkan.');
                }
            }
            if (! $model->workflowMutation && ($stored->status !== 'DRAFT' || $model->isDirty(['status', 'dibekukan_awal_pada', 'dibekukan_pada']))) {
                throw new BusinessRuleException('asesmen_bukan_draft', 'Status asesmen hanya dapat diubah melalui workflow.');
            }

            return;
        }
        $field = $model instanceof PenilaianRisiko ? 'risiko_id' : ($model instanceof UsulanKeputusan ? 'penilaian_id' : 'asesmen_id');
        if ($stored && (int) $stored->$field !== (int) $model->$field) {
            throw new BusinessRuleException('induk_asesmen_tetap', 'Data anak tidak dapat dipindahkan ke induk lain.');
        }
        $risk = $model instanceof PenilaianRisiko ? Risiko::findOrFail($model->risiko_id) : ($model instanceof UsulanKeputusan ? Risiko::findOrFail(PenilaianRisiko::findOrFail($model->penilaian_id)->risiko_id) : null);
        $assessment = AsesmenRisiko::whereKey($risk?->asesmen_id ?? $model->asesmen_id)->lockForUpdate()->firstOrFail();
        if ($assessment->dibekukan_pada) {
            throw new BusinessRuleException('asesmen_dibekukan', 'Asesmen sudah dibekukan.');
        }
        $residual = $model instanceof PenilaianRisiko && $model->tahap === 'AKTUAL_SETELAH';
        if ($residual && (! $stored && ! $delete)) {
            if ($assessment->status !== 'RESIDUAL_DRAFT') {
                throw new BusinessRuleException('residual_bukan_draft', 'Penilaian aktual hanya dapat ditambahkan pada RESIDUAL_DRAFT.');
            }
        } elseif ($assessment->status !== 'DRAFT' || $assessment->dibekukan_awal_pada) {
            throw new BusinessRuleException('asesmen_bukan_draft', 'Bagian awal asesmen sudah tidak dapat diubah.');
        }
        if ($model instanceof AsesmenAset && $stored) {
            foreach (['aset_id', 'nama_snapshot', 'kategori_snapshot', 'klasifikasi_informasi_snapshot', 'jalur_unit_snapshot', 'lokasi_snapshot', 'direkam_pada'] as $snapshot) {
                if ($model->isDirty($snapshot)) {
                    throw new BusinessRuleException('snapshot_tetap', 'Snapshot aset tidak dapat diubah.');
                }
            }
        }
        if ($model instanceof PenilaianRisiko && ! $delete) {
            $cell = MatriksRisiko::findOrFail($model->matriks_id);
            if ($cell->versi_metode_id !== $assessment->versi_metode_id) {
                throw new BusinessRuleException('matriks_beda_versi', 'Matriks harus berasal dari metode asesmen.');
            }
        }
    }
}
