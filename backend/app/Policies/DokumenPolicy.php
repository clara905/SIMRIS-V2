<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ScopeUnit;
use Illuminate\Auth\Access\Response;

class DokumenPolicy
{
    public function __construct(private ScopeUnit $scope) {}

    public function view(User $user): Response
    {
        return $this->scope->hasRole($user, ['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER']) ? Response::allow() : Response::deny('Role aktif diperlukan untuk membaca dokumen.');
    }

    public function write(User $user, int $unit): Response
    {
        return in_array($unit, $this->scope->idsForRoles($user, ['ADMIN_SATKER', 'STAFF']), true) ? Response::allow() : Response::deny('Hanya penyusun dalam scope dapat mengubah dokumen.');
    }
}
