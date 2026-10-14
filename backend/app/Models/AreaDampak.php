<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\MetodeRisikoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([MetodeRisikoObserver::class])]
class AreaDampak extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'AREA_DAMPAK';

    protected $fillable = ['kode', 'nama'];

    protected function casts(): array
    {
        return [];
    }
}
