<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\KepatuhanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([KepatuhanObserver::class])]
class SoaDetail extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    protected $table = 'SOA_DETAIL';

    protected $fillable = ['soa_versi_id', 'kontrol_id', 'penanggung_jawab_id', 'berlaku', 'alasan', 'status_penerapan'];

    protected function casts(): array
    {
        return ['soa_versi_id' => 'integer', 'kontrol_id' => 'integer', 'penanggung_jawab_id' => 'integer', 'berlaku' => 'boolean'];
    }
}
