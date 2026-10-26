<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\DokumenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([DokumenObserver::class])]
class DokumenVersi extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'DOKUMEN_VERSI';

    protected $fillable = ['dokumen_id', 'dibuat_oleh', 'nomor_versi', 'isi_teks', 'format_teks', 'status', 'dibuat_pada', 'dibekukan_pada'];

    protected function casts(): array
    {
        return ['dokumen_id' => 'integer', 'dibuat_oleh' => 'integer', 'nomor_versi' => 'integer', 'dibuat_pada' => 'immutable_datetime', 'dibekukan_pada' => 'immutable_datetime'];
    }
}
