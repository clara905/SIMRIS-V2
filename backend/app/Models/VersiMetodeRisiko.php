<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\MetodeRisikoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MetodeRisikoObserver::class])]
class VersiMetodeRisiko extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'VERSI_METODE_RISIKO';

    protected $fillable = ['nama', 'versi', 'referensi', 'dibekukan'];

    protected function casts(): array
    {
        return ['dibekukan' => 'boolean'];
    }
}
