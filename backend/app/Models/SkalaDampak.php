<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\MetodeRisikoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MetodeRisikoObserver::class])]
class SkalaDampak extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'SKALA_DAMPAK';

    protected $fillable = ['versi_metode_id', 'nilai', 'label', 'kriteria'];

    protected function casts(): array
    {
        return ['versi_metode_id' => 'integer', 'nilai' => 'integer'];
    }
}
