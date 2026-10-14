<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodePenilaian extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'PERIODE_PENILAIAN';

    protected $fillable = ['nama', 'tanggal_mulai', 'tanggal_selesai'];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date:Y-m-d', 'tanggal_selesai' => 'date:Y-m-d'];
    }
}
