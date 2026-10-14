<?php

namespace App\Http\Resources;

use App\Models\Risiko;
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

        return $data;
    }
}
