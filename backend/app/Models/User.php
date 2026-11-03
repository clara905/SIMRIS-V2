<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Auditable;
    use SerializesLocalDates;

    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'pegawai_id',
        'username',
        'email_login',
        'password_hash',
        'aktif',
        'wajib_ganti_password',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $authPasswordName = 'password_hash';

    protected $rememberTokenName = '';

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'aktif' => 'boolean',
            'wajib_ganti_password' => 'boolean',
        ];
    }

    protected function emailLogin(): Attribute
    {
        return Attribute::make(
            set: fn (string $nilai) => mb_strtolower(trim($nilai))
        );
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(UserRoleScope::class, 'user_id');
    }

    public function penugasanAktif(): HasMany
    {
        return $this->penugasan()->aktif();
    }

    protected static function booted(): void
    {
        static::deleting(function () {
            throw new BusinessRuleException(
                'hapus_fisik_dilarang',
                'Gunakan penonaktifan untuk menjaga riwayat data.'
            );
        });
    }
}