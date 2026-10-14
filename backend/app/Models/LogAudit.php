<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class LogAudit extends Model
{
    use SerializesLocalDates;

    protected $table = 'LOG_AUDIT';

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['data_lama' => 'array', 'data_baru' => 'array', 'waktu' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn () => throw new LogicException('Gunakan AuditService untuk menambah audit.'));
        static::deleting(fn () => throw new LogicException('Log audit tidak dapat dihapus.'));
    }
}
