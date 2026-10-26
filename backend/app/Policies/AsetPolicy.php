<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ScopeUnit;
use Illuminate\Auth\Access\Response;

class AsetPolicy
{
    public function __construct(private ScopeUnit $scope) {}

    public function view(User $user): Response
    {
        return $this->scope->hasRole($user, ['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']) ? Response::allow() : Response::deny('Role aktif diperlukan untuk membaca aset.');
    }

    public function write(User $user, int $unit): Response
    {
        return in_array($unit, $this->scope->idsForRoles($user, ['ADMIN_SATKER']), true) ? Response::allow() : Response::deny('Hanya ADMIN_SATKER dalam scope aset dapat mengubah data lokal.');
    }

    public function sync(User $user): Response
    {
        return $this->scope->isSuper($user) ? Response::allow() : Response::deny('Riwayat dan pemicu sinkron hanya untuk SUPER_ADMIN.');
    }
}
