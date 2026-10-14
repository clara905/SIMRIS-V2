<?php

namespace App\Models;

use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewAsesmen extends Model
{
    use Auditable,HasFactory,SerializesLocalDates;

    protected $table = 'REVIEW_ASESMEN';

    protected $fillable = ['asesmen_id', 'reviewer_id', 'tahap', 'keputusan', 'catatan', 'kembali_ke', 'waktu'];

    protected function casts(): array
    {
        return ['asesmen_id' => 'integer', 'reviewer_id' => 'integer', 'waktu' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new BusinessRuleException('review_tetap', 'Review tidak dapat diubah.'));
        static::deleting(fn () => throw new BusinessRuleException('review_tetap', 'Review tidak dapat dihapus.'));
    }
}
