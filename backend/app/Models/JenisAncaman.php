<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisAncaman extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'JENIS_ANCAMAN';

    protected $fillable = ['kode', 'nama'];

    protected function casts(): array
    {
        return [];
    }
}
