<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\AsesmenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AsesmenObserver::class])]
class PenilaianRisiko extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'PENILAIAN_RISIKO';

    protected $fillable = ['risiko_id', 'matriks_id', 'dinilai_oleh', 'tahap', 'alasan_kemungkinan', 'alasan_dampak', 'kesimpulan', 'dinilai_pada'];

    protected function casts(): array
    {
        return ['risiko_id' => 'integer', 'matriks_id' => 'integer', 'dinilai_oleh' => 'integer', 'dinilai_pada' => 'immutable_datetime'];
    }
}
