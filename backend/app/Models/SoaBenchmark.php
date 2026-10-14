<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\KepatuhanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([KepatuhanObserver::class])]
class SoaBenchmark extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    protected $table = 'SOA_BENCHMARK';

    protected $fillable = ['soa_detail_id', 'regulasi_id', 'rujukan'];

    protected function casts(): array
    {
        return ['soa_detail_id' => 'integer', 'regulasi_id' => 'integer'];
    }
}
