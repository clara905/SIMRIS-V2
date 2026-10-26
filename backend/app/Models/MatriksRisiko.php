<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\MetodeRisikoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MetodeRisikoObserver::class])]
class MatriksRisiko extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'MATRIKS_RISIKO';

    protected $fillable = ['versi_metode_id', 'kemungkinan_id', 'dampak_id', 'tingkat_risiko_id', 'skor', 'rekomendasi_keputusan'];

    protected function casts(): array
    {
        return ['versi_metode_id' => 'integer', 'kemungkinan_id' => 'integer', 'dampak_id' => 'integer', 'tingkat_risiko_id' => 'integer', 'skor' => 'integer'];
    }
}
