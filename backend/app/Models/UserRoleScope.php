<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Carbon\CarbonInterface;
use Database\Factories\UserRoleScopeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleScope extends Model
{
    use Auditable, SerializesLocalDates;

    /** @use HasFactory<UserRoleScopeFactory> */
    use HasFactory;

    protected $table = 'USER_ROLE_SCOPE';

    protected $fillable = [
        'user_id',
        'role_id',
        'unit_id',
        'mulai',
        'selesai',
    ];

    protected function casts(): array
    {
        return [
            'mulai' => 'immutable_datetime',
            'selesai' => 'immutable_datetime',
        ];
    }

    /**
     * Scope untuk mengambil role yang masih aktif.
     */
    public function scopeAktif(
        Builder $query,
        ?CarbonInterface $sekarang = null
    ): Builder {
        $sekarang ??= now();

        return $query
            ->where('mulai', '<=', $sekarang)
            ->where(function (Builder $q) use ($sekarang) {
                $q->whereNull('selesai')
                    ->orWhere('selesai', '>', $sekarang);
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOrganisasi::class, 'unit_id');
    }
}
