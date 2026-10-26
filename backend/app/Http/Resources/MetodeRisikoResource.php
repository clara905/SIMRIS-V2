<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetodeRisikoResource extends JsonResource
{
    /**
     * @return array{id: int, nama?: string, versi?: string, referensi?: string, dibekukan?: bool, versi_metode_id?: int, nilai?: int, label?: string, label_persentase?: string, label_frekuensi?: string, kriteria?: string, kode?: string, skala_dampak_id?: int, area_dampak_id?: int, arah_dampak?: string, deskripsi?: string, warna?: string, kemungkinan_id?: int, dampak_id?: int, tingkat_risiko_id?: int, skor?: int, rekomendasi_keputusan?: string, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
