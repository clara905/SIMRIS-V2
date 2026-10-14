<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aset extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'ASET';

    protected $fillable = ['sumber_id', 'external_id', 'unit_pemilik_id', 'kategori_id', 'klasifikasi_informasi_id', 'kode_aset', 'nama', 'deskripsi', 'merk', 'nomor_seri', 'jumlah', 'kondisi', 'lokasi', 'tanggal_pengadaan', 'status', 'last_synced_at', 'nomor_inventaris', 'nama_pengadaan', 'tahun_pengadaan', 'keterangan', 'data_mentah', 'hash_data', 'perlu_ditinjau'];

    protected $hidden = ['data_mentah', 'hash_data'];

    protected function casts(): array
    {
        return ['sumber_id' => 'integer', 'external_id' => 'integer', 'unit_pemilik_id' => 'integer', 'kategori_id' => 'integer', 'klasifikasi_informasi_id' => 'integer', 'jumlah' => 'integer', 'tanggal_pengadaan' => 'date:Y-m-d', 'last_synced_at' => 'immutable_datetime', 'tahun_pengadaan' => 'integer', 'data_mentah' => 'array', 'perlu_ditinjau' => 'boolean'];
    }
}
