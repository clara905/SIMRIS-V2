<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kerentanan extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'KERENTANAN';

    protected $fillable = ['jenis_kerentanan_id', 'kode', 'nama', 'deskripsi'];

    protected function casts(): array
    {
        return ['jenis_kerentanan_id' => 'integer'];
    }
}
