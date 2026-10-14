<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeriAsesmen extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'SERI_ASESMEN';

    protected $fillable = ['kode', 'nama'];

    protected function casts(): array
    {
        return [];
    }
}
