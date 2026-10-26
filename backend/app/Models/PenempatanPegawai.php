<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\PenempatanPegawaiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenempatanPegawai extends Model
{
    use Auditable, SerializesLocalDates;

    /** @use HasFactory<PenempatanPegawaiFactory> */
    use HasFactory;

    protected $table = 'PENEMPATAN_PEGAWAI';

    protected $fillable = ['pegawai_id', 'unit_id', 'mulai', 'selesai', 'utama'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mulai' => 'date:Y-m-d', 'selesai' => 'date:Y-m-d', 'utama' => 'boolean'];
    }
}
