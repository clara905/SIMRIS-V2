<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KlasifikasiInformasi extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'KLASIFIKASI_INFORMASI';

    protected $fillable = ['kode', 'nama', 'deskripsi'];

    protected function casts(): array
    {
        return [];
    }
}
