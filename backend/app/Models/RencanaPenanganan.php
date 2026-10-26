<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\PenangananObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([PenangananObserver::class])]
class RencanaPenanganan extends Model
{
    use Auditable, HasFactory, SerializesLocalDates;

    protected $table = 'RENCANA_PENANGANAN';

    protected $fillable = ['asesmen_id', 'rencana_kerja_id', 'pic_id', 'kode', 'uraian', 'target_selesai', 'status'];

    protected function casts(): array
    {
        return ['asesmen_id' => 'integer', 'rencana_kerja_id' => 'integer', 'pic_id' => 'integer', 'target_selesai' => 'date:Y-m-d'];
    }
}
