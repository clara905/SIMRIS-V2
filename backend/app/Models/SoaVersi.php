<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\KepatuhanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([KepatuhanObserver::class])]
class SoaVersi extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    public bool $workflowMutation = false;

    protected $table = 'SOA_VERSI';

    protected $fillable = ['soa_id', 'dibuat_oleh', 'nomor_versi', 'status', 'dibuat_pada', 'dibekukan_pada'];

    protected function casts(): array
    {
        return ['soa_id' => 'integer', 'dibuat_oleh' => 'integer', 'dibuat_pada' => 'datetime', 'dibekukan_pada' => 'datetime'];
    }
}
