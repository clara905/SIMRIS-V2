<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisKerentanan extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'JENIS_KERENTANAN';

    protected $fillable = ['kode', 'nama'];

    protected function casts(): array
    {
        return [];
    }
}
