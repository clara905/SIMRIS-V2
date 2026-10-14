<?php

namespace App\Http\Resources;

use App\Models\KeputusanRisiko;
use App\Models\PenilaianRisiko;
use App\Models\RencanaPenanganan;
use App\Models\Risiko;
use App\Models\UsulanKeputusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** @mixin Risiko */
class LampiranRisikoResource extends AsesmenResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $ratings = PenilaianRisiko::where('risiko_id', $this->id)->orderByDesc('id')->get();
        $data['penilaian'] = $ratings->toArray();
        $data['usulan_keputusan'] = UsulanKeputusan::whereIn('penilaian_id', $ratings->pluck('id'))->get()->toArray();
        $data['keputusan_risiko'] = KeputusanRisiko::whereIn('penilaian_id', $ratings->pluck('id'))->get()->toArray();
        $data['rencana_penanganan'] = RencanaPenanganan::where('asesmen_id', $this->asesmen_id)->whereIn('id', DB::table('RISIKO_PENANGANAN')->where('risiko_id', $this->id)->select('rencana_penanganan_id'))->get()->toArray();

        return $data;
    }
}
