<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\DokumenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([DokumenObserver::class])]
class Dokumen extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'DOKUMEN';

    protected $fillable = ['unit_pemilik_id', 'dibuat_oleh', 'kode', 'judul', 'jenis', 'dibuat_pada'];

    protected function casts(): array
    {
        return ['unit_pemilik_id' => 'integer', 'dibuat_oleh' => 'integer', 'dibuat_pada' => 'immutable_datetime'];
    }
}
