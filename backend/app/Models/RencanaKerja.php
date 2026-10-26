<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RencanaKerja extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'RENCANA_KERJA';

    protected $fillable = ['kode', 'uraian_template'];

    protected function casts(): array
    {
        return [];
    }
}
