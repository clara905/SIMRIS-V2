<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kontrol extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'KONTROL';

    protected $fillable = ['standar_id', 'parent_id', 'kode', 'nama', 'jenis', 'deskripsi'];

    protected function casts(): array
    {
        return ['standar_id' => 'integer', 'parent_id' => 'integer'];
    }
}
