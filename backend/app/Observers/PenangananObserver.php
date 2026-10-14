<?php

namespace App\Observers;

use App\Exceptions\BusinessRuleException;
use App\Models\AsesmenRisiko;
use App\Models\KeputusanRisiko;
use App\Models\PenilaianRisiko;
use App\Models\ProgresPenanganan;
use App\Models\RencanaPenanganan;
use App\Models\Risiko;
use Illuminate\Database\Eloquent\Model;

class PenangananObserver
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
        if ($model instanceof KeputusanRisiko) {
            $parent = Risiko::findOrFail(PenilaianRisiko::findOrFail($model->penilaian_id)->risiko_id)->asesmen_id;
        } else {
            $parent = $model instanceof ProgresPenanganan ? RencanaPenanganan::findOrFail($model->rencana_penanganan_id)->asesmen_id : $model->asesmen_id;
        }
        $assessment = AsesmenRisiko::whereKey($parent)->lockForUpdate()->firstOrFail();
        if ($assessment->dibekukan_pada) {
            throw new BusinessRuleException('asesmen_dibekukan', 'Asesmen sudah dibekukan.');
        }
        if ($model instanceof KeputusanRisiko) {
            if ($model->exists || $delete || $assessment->status !== 'MENUNGGU_KASATKER') {
                throw new BusinessRuleException('keputusan_tetap', 'Keputusan hanya dibuat saat pengesahan akhir dan tidak dapat diubah.');
            }

return;
        }
        if ($assessment->status !== 'PENANGANAN') {
            throw new BusinessRuleException('bukan_tahap_penanganan', 'Penanganan hanya dapat diubah saat status PENANGANAN.');
        }
        if ($model->exists) {
            $stored = $model->newQuery()->whereKey($model->id)->lockForUpdate()->firstOrFail();
            if ($model instanceof ProgresPenanganan) {
                throw new BusinessRuleException('progres_tetap', 'Riwayat progres tidak dapat diubah atau dihapus.');
            }
            if ((int) $stored->asesmen_id !== (int) $model->asesmen_id) {
                throw new BusinessRuleException('induk_asesmen_tetap', 'Rencana tidak dapat dipindahkan ke asesmen lain.');
            }
        }
    }
}
