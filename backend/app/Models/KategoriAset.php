<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriAset extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'KATEGORI_ASET';

    protected $fillable = ['parent_id', 'kode', 'nama'];

    protected function casts(): array
    {
        return ['parent_id' => 'integer'];
    }
}
