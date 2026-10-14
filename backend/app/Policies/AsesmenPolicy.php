<?php

namespace App\Policies;

use App\Models\AsesmenRisiko;
use App\Models\User;
use App\Services\ScopeUnit;
use Illuminate\Auth\Access\Response;

class AsesmenPolicy
{
    public function __construct(private ScopeUnit $scope) {}

    public function view(User $user): Response
    {
        return $this->scope->hasRole($user, ['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER']) ? Response::allow() : Response::deny('Role aktif diperlukan untuk membaca asesmen.');
    }

    public function create(User $user, int $unit): Response
    {
        return in_array($unit, $this->scope->idsForRoles($user, ['ADMIN_SATKER', 'STAFF']), true) ? Response::allow() : Response::deny('Hanya penyusun dalam scope dapat membuat asesmen.');
    }

    public function write(User $user, AsesmenRisiko $assessment): Response
    {
        return in_array($assessment->unit_id, $this->scope->idsForRoles($user, ['ADMIN_SATKER']), true) || ($assessment->dibuat_oleh === $user->id && in_array($assessment->unit_id, $this->scope->idsForRoles($user, ['STAFF']), true)) ? Response::allow() : Response::deny('Hanya penyusun atau ADMIN_SATKER dalam scope dapat mengubah asesmen.');
    }
}
