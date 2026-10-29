<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\UserRoleScopeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRoleScope extends Model
{
    use Auditable, SerializesLocalDates;

    /** Admin satker menulis audit berpenanda secara eksplisit dari AdminService. */
    public bool $suppressAutomaticAudit = false;

    /** @use HasFactory<UserRoleScopeFactory> */
    use HasFactory;

    protected $table = 'USER_ROLE_SCOPE';

    protected $fillable = ['user_id', 'role_id', 'unit_id', 'mulai', 'selesai'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mulai' => 'immutable_datetime', 'selesai' => 'immutable_datetime'];
    }
}
