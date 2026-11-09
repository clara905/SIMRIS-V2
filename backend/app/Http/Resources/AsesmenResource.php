<?php

namespace App\Http\Resources;

use App\Models\MatriksRisiko;
use App\Models\PenilaianRisiko;
use App\Models\Risiko;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\TingkatRisiko;
use App\Models\VersiMetodeRisiko;
use App\Services\HitungRisiko;
use App\Services\KeputusanService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AsesmenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        if ($this->resource instanceof Risiko) {
            $data['residual_tercapai'] = app(KeputusanService::class)->residualAchieved($this->resource);
            $data['asesmen_aset_ids'] = DB::table('RISIKO_ASET')->where('risiko_id', $this->id)->pluck('asesmen_aset_id')->all();
        }
        if ($this->resource instanceof PenilaianRisiko) {
            $cell = MatriksRisiko::find($this->matriks_id);
            if ($cell) {
                $possibility = SkalaKemungkinan::find($cell->kemungkinan_id);
                $impact = SkalaDampak::find($cell->dampak_id);
                $level = TingkatRisiko::find($cell->tingkat_risiko_id);
                $methodId = DB::table('ASESMEN_RISIKO')
                    ->join('RISIKO', 'RISIKO.asesmen_id', '=', 'ASESMEN_RISIKO.id')
                    ->where('RISIKO.id', $this->risiko_id)
                    ->value('ASESMEN_RISIKO.versi_metode_id');
                $method = $methodId ? VersiMetodeRisiko::find($methodId) : null;
                if ($possibility && $impact && $level && $method) {
                    $data['matriks'] = $cell;
                    $data['kemungkinan'] = $possibility;
                    $data['dampak'] = $impact;
                    $data['tingkat'] = $level;
                    if ($method->profil_penilaian) {
                        $calculated = app(HitungRisiko::class)->fromCell($method, $cell, $possibility, $impact, $level);
                        $data['skor'] = $calculated['skor'];
                        $data['perlu_rtp'] = $calculated['perlu_rtp'];
                        $data['perlu_persetujuan_pemilik'] = $calculated['perlu_persetujuan_pemilik'];
                        $data['kriteria_penerimaan'] = $calculated['kriteria_penerimaan'];
                    } else {
                        $data['skor'] = $cell->skor;
                        $data['perlu_rtp'] = null;
                        $data['perlu_persetujuan_pemilik'] = null;
                        $data['kriteria_penerimaan'] = null;
                    }
                    $data['tingkat_label'] = $level->nama;
                }
            }
        }

        return $data;
    }
}
