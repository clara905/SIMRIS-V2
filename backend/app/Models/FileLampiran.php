<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\DokumenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([DokumenObserver::class])]
class FileLampiran extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'FILE_LAMPIRAN';

    protected $fillable = ['diunggah_oleh', 'nama_asli', 'storage_key', 'ekstensi', 'mime_type_terdeteksi', 'ukuran_byte', 'checksum_sha256', 'status_pemeriksaan', 'diunggah_pada'];

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return ['diunggah_oleh' => 'integer', 'ukuran_byte' => 'integer', 'diunggah_pada' => 'immutable_datetime'];
    }
}
