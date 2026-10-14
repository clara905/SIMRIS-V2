<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\KepatuhanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([KepatuhanObserver::class])]
class SopVersi extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    public bool $workflowMutation = false;

    protected $table = 'SOP_VERSI';

    protected $fillable = ['sop_id', 'dokumen_versi_id', 'dibuat_oleh', 'nomor_versi', 'tanggal_berlaku', 'status', 'dibuat_pada'];

    protected function casts(): array
    {
        return ['sop_id' => 'integer', 'dokumen_versi_id' => 'integer', 'dibuat_oleh' => 'integer', 'tanggal_berlaku' => 'date:Y-m-d', 'dibuat_pada' => 'datetime'];
    }
}
