<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SinkronAset extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    public $timestamps = false;

    protected $table = 'SINKRON_ASET';

    protected $fillable = ['sumber_id', 'dipicu_oleh', 'dimulai_pada', 'selesai_pada', 'status', 'jumlah_diterima', 'jumlah_dibuat', 'jumlah_diubah', 'jumlah_tidak_berubah', 'jumlah_tidak_ditemukan', 'jumlah_ditolak', 'peringatan', 'galat'];

    protected function casts(): array
    {
        return ['sumber_id' => 'integer', 'dipicu_oleh' => 'integer', 'dimulai_pada' => 'immutable_datetime', 'selesai_pada' => 'immutable_datetime', 'jumlah_diterima' => 'integer', 'jumlah_dibuat' => 'integer', 'jumlah_diubah' => 'integer', 'jumlah_tidak_berubah' => 'integer', 'jumlah_tidak_ditemukan' => 'integer', 'jumlah_ditolak' => 'integer', 'peringatan' => 'array'];
    }
}
