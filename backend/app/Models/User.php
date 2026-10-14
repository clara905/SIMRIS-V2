<?php

namespace App\Models;

use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Auditable, SerializesLocalDates;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'USERS';

    protected $fillable = ['pegawai_id', 'username', 'email_login', 'password_hash', 'aktif', 'wajib_ganti_password'];

    protected $hidden = ['password_hash'];

    protected $authPasswordName = 'password_hash';

    protected $rememberTokenName = '';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['password_hash' => 'hashed', 'aktif' => 'boolean', 'wajib_ganti_password' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('hapus_fisik_dilarang', 'Gunakan penonaktifan untuk menjaga riwayat data.'));
    }
}
