<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Regulasi extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'REGULASI';

    protected $fillable = ['kode', 'nama', 'referensi'];

    protected function casts(): array
    {
        return [];
    }
}
