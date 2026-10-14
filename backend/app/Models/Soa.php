<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\KepatuhanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([KepatuhanObserver::class])]
class Soa extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    protected $table = 'SOA';

    protected $fillable = ['unit_id', 'periode_id', 'standar_id', 'ruang_lingkup'];

    protected function casts(): array
    {
        return ['unit_id' => 'integer', 'periode_id' => 'integer', 'standar_id' => 'integer'];
    }
}
