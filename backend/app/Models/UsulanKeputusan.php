<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\AsesmenObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AsesmenObserver::class])]
class UsulanKeputusan extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'USULAN_KEPUTUSAN';

    protected $fillable = ['penilaian_id', 'diusulkan_oleh', 'keputusan', 'alasan', 'diusulkan_pada'];

    protected function casts(): array
    {
        return ['penilaian_id' => 'integer', 'diusulkan_oleh' => 'integer', 'diusulkan_pada' => 'immutable_datetime'];
    }
}
