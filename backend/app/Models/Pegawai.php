<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\PegawaiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    use Auditable, SerializesLocalDates;

    /** @use HasFactory<PegawaiFactory> */
    use HasFactory;

    protected $table = 'PEGAWAI';

    protected $fillable = ['nip', 'nama', 'email_kontak', 'jenis_kepegawaian', 'aktif'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

}
