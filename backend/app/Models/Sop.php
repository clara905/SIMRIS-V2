<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\KepatuhanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([KepatuhanObserver::class])]
class Sop extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    protected $table = 'SOP';

    protected $fillable = ['unit_pemilik_id', 'kode', 'judul'];

    protected function casts(): array
    {
        return ['unit_pemilik_id' => 'integer'];
    }
}
