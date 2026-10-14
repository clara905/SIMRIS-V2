<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\KepatuhanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([KepatuhanObserver::class])]
class KontrolInternal extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    protected $table = 'KONTROL_INTERNAL';

    protected $fillable = ['unit_pemilik_id', 'kode', 'nama', 'jenis', 'deskripsi', 'aktif'];

    protected function casts(): array
    {
        return ['unit_pemilik_id' => 'integer', 'aktif' => 'boolean'];
    }
}
