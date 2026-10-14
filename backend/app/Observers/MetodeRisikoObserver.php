<?php

namespace App\Observers;

use App\Exceptions\BusinessRuleException;
use App\Models\AreaDampak;
use App\Models\KriteriaDampak;
use App\Models\MatriksRisiko;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\VersiMetodeRisiko;
use App\Services\MetodeRisikoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MetodeRisikoObserver
{
    public function saving(Model $model): void
    {
        $this->guard($model, false);
    }

    public function deleting(Model $model): void
    {
        $this->guard($model, true);
    }

    private function guard(Model $model, bool $deleting): void
    {
        $service = app(MetodeRisikoService::class);
        $service->lock();
        $stored = $model->exists ? $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail() : null;
        if ($model instanceof AreaDampak) {
            if ($stored && DB::table('KRITERIA_DAMPAK as k')->join('SKALA_DAMPAK as s', 's.id', '=', 'k.skala_dampak_id')->join('VERSI_METODE_RISIKO as v', 'v.id', '=', 's.versi_metode_id')->where('k.area_dampak_id', $model->id)->where('v.dibekukan', true)->exists()) {
                throw new BusinessRuleException('versi_sudah_beku', 'Area dampak dipakai versi beku dan tidak dapat diubah.');
            }

            return;
        }
        if ($model instanceof VersiMetodeRisiko) {
            if ($stored?->dibekukan) {
                throw new BusinessRuleException('versi_sudah_beku', 'Versi metode sudah beku dan tidak dapat diubah.');
            }
            if ($deleting && DB::table('ASESMEN_RISIKO')->where('versi_metode_id', $model->id)->exists()) {
                throw new BusinessRuleException('versi_dipakai_asesmen', 'Versi yang dipakai asesmen tidak dapat dihapus.');
            }
            if (! $deleting && $model->dibekukan) {
                if (! $stored) {
                    throw new BusinessRuleException('metode_belum_valid', 'Buat versi draft sebelum membekukan.');
                }
                $service->assertValid($stored);
            }

            return;
        }
        $parent = $model instanceof KriteriaDampak ? 'skala_dampak_id' : 'versi_metode_id';
        foreach (array_unique(array_filter([$stored?->$parent, $model->$parent])) as $parentId) {
            $versionId = $model instanceof KriteriaDampak ? SkalaDampak::findOrFail($parentId)->versi_metode_id : $parentId;
            $service->assertDraft((int) $versionId);
        }
        if ($stored && (int) $stored->$parent !== (int) $model->$parent) {
            throw new BusinessRuleException('induk_metode_tetap', 'Induk data metode tidak dapat dipindahkan; buat data baru.');
        }
        if ($model instanceof SkalaKemungkinan || $model instanceof SkalaDampak) {
            $values = $model->newQuery()->where('versi_metode_id', $model->versi_metode_id)->when($stored, fn ($q) => $q->where('id', '!=', $model->id))->pluck('nilai')->all();
            if (! $deleting) {
                $values[] = (int) $model->nilai;
            }
            sort($values);
            if ($values !== ($values ? range(1, count($values)) : [])) {
                throw new BusinessRuleException('skala_tidak_berurutan', 'Nilai skala harus unik dan berurutan mulai dari 1.');
            }
        }
        if (! $deleting && $model instanceof MatriksRisiko) {
            $service->prepareCell($model);
        }
    }
}
