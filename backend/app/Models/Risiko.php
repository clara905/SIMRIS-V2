<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\AsesmenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AsesmenObserver::class])]
class Risiko extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'RISIKO';

    protected $fillable = ['asesmen_id', 'ancaman_id', 'kerentanan_id', 'area_dampak_id', 'pemilik_risiko_id', 'dibuat_oleh', 'kode', 'skenario', 'uraian_kerentanan', 'uraian_dampak', 'arah_dampak', 'dibuat_pada'];

    protected function casts(): array
    {
        return ['asesmen_id' => 'integer', 'ancaman_id' => 'integer', 'kerentanan_id' => 'integer', 'area_dampak_id' => 'integer', 'pemilik_risiko_id' => 'integer', 'dibuat_oleh' => 'integer', 'dibuat_pada' => 'immutable_datetime'];
    }
}
