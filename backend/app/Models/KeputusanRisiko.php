<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\PenangananObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([PenangananObserver::class])]
class KeputusanRisiko extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'KEPUTUSAN_RISIKO';

    protected $fillable = ['penilaian_id', 'diputuskan_oleh', 'keputusan', 'alasan', 'diputuskan_pada'];

    protected function casts(): array
    {
        return ['penilaian_id' => 'integer', 'diputuskan_oleh' => 'integer', 'diputuskan_pada' => 'immutable_datetime'];
    }
}
