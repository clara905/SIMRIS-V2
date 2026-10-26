<?php

namespace App\Models;

use App\Exceptions\BusinessRuleException;
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

    protected $fillable = ['parent_id', 'kode', 'nama', 'jenis', 'aktif'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('hapus_fisik_dilarang', 'Gunakan penonaktifan untuk menjaga riwayat data.'));
    }
}
