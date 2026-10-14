<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\PenangananObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([PenangananObserver::class])]
class ProgresPenanganan extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'PROGRES_PENANGANAN';

    protected $fillable = ['rencana_penanganan_id', 'dilaporkan_oleh', 'uraian', 'status', 'dilaporkan_pada'];

    protected function casts(): array
    {
        return ['rencana_penanganan_id' => 'integer', 'dilaporkan_oleh' => 'integer', 'dilaporkan_pada' => 'immutable_datetime'];
    }
}
