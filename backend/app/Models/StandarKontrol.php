<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StandarKontrol extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'STANDAR_KONTROL';

    protected $fillable = ['nama', 'versi', 'referensi'];

    protected function casts(): array
    {
        return [];
    }
}
