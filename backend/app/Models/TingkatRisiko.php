<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\MetodeRisikoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MetodeRisikoObserver::class])]
class TingkatRisiko extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'TINGKAT_RISIKO';

    protected $fillable = ['versi_metode_id', 'kode', 'nama', 'warna', 'batas_min_skor', 'batas_max_skor', 'perlu_rtp', 'perlu_persetujuan_pemilik', 'kriteria_penerimaan'];

    protected function casts(): array
    {
        return ['versi_metode_id' => 'integer', 'batas_min_skor' => 'integer', 'batas_max_skor' => 'integer', 'perlu_rtp' => 'boolean', 'perlu_persetujuan_pemilik' => 'boolean'];
    }
}
