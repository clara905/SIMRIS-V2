<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\AsesmenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AsesmenObserver::class])]
class AsesmenRisiko extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    public bool $workflowMutation = false;

    protected $table = 'ASESMEN_RISIKO';

    protected $fillable = ['seri_asesmen_id', 'versi_sebelumnya_id', 'periode_id', 'unit_id', 'versi_metode_id', 'dibuat_oleh', 'tujuan', 'ruang_lingkup', 'status', 'nomor_versi', 'dibuat_pada', 'dibekukan_pada', 'dibekukan_awal_pada'];

    protected function casts(): array
    {
        return ['seri_asesmen_id' => 'integer', 'versi_sebelumnya_id' => 'integer', 'periode_id' => 'integer', 'unit_id' => 'integer', 'versi_metode_id' => 'integer', 'dibuat_oleh' => 'integer', 'nomor_versi' => 'integer', 'dibuat_pada' => 'immutable_datetime', 'dibekukan_pada' => 'immutable_datetime', 'dibekukan_awal_pada' => 'immutable_datetime'];
    }
}
