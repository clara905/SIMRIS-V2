<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ancaman extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'ANCAMAN';

    protected $fillable = ['jenis_ancaman_id', 'kode', 'nama', 'deskripsi'];

    protected function casts(): array
    {
        return ['jenis_ancaman_id' => 'integer'];
    }
}
