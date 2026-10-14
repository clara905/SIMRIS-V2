<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\AsesmenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AsesmenObserver::class])]
class AsesmenAset extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'ASESMEN_ASET';

    protected $fillable = ['asesmen_id', 'aset_id', 'nama_snapshot', 'kategori_snapshot', 'klasifikasi_informasi_snapshot', 'jalur_unit_snapshot', 'lokasi_snapshot', 'nilai_kekritisan', 'dasar_kekritisan', 'direkam_pada'];

    protected function casts(): array
    {
        return ['asesmen_id' => 'integer', 'aset_id' => 'integer', 'nilai_kekritisan' => 'decimal:4', 'direkam_pada' => 'immutable_datetime'];
    }
}
