<?php

namespace App\Http\Resources;

use App\Models\Ancaman;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class KatalogResource extends JsonResource
{
    /** @return array{id:int,kode?:string,nama:string,deskripsi?:string,referensi?:string,versi?:string,parent_id?:int|null,standar_id?:int,jenis?:string,jenis_ancaman_id?:int,jenis_kerentanan_id?:int,created_at:string,updated_at:string} */
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        if ($this->resource instanceof Ancaman) {
            $data['sumber_ids'] = DB::table('ANCAMAN_SUMBER')->where('ancaman_id', $this->id)->pluck('sumber_ancaman_id')->all();
        }

return $data;
    }
}
