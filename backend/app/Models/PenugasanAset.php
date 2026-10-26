<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenugasanAset extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'PENUGASAN_ASET';

    protected $fillable = ['aset_id', 'pegawai_id', 'jenis_tanggung_jawab', 'mulai', 'selesai'];

    protected function casts(): array
    {
        return ['aset_id' => 'integer', 'pegawai_id' => 'integer', 'mulai' => 'date:Y-m-d', 'selesai' => 'date:Y-m-d'];
    }
}
