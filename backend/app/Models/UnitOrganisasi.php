<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\UnitOrganisasiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitOrganisasi extends Model
{
    use Auditable, SerializesLocalDates;

    /** @use HasFactory<UnitOrganisasiFactory> */
    use HasFactory;

    protected $table = 'UNIT_ORGANISASI';

    protected $fillable = [
        'parent_id',
        'kode',
        'nama',
        'jenis',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }
}
