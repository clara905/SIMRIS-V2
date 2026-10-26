<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\MetodeRisikoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MetodeRisikoObserver::class])]
class KriteriaDampak extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'KRITERIA_DAMPAK';

    protected $fillable = ['skala_dampak_id', 'area_dampak_id', 'arah_dampak', 'deskripsi'];

    protected function casts(): array
    {
        return ['skala_dampak_id' => 'integer', 'area_dampak_id' => 'integer'];
    }
}
