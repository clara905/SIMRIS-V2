<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use App\Observers\KepatuhanObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([KepatuhanObserver::class])]
class SopReview extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    protected $table = 'SOP_REVIEW';

    protected $fillable = ['sop_versi_id', 'reviewer_id', 'tahap', 'keputusan', 'catatan', 'waktu'];

    protected function casts(): array
    {
        return ['sop_versi_id' => 'integer', 'reviewer_id' => 'integer', 'waktu' => 'datetime'];
    }
}
