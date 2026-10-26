<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ScopeUnit;
use Illuminate\Auth\Access\Response;

class KatalogPolicy
{
    public function __construct(private ScopeUnit $scope) {}

    public function view(User $user): Response
    {
        return $this->scope->hasRole($user, ['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']) ? Response::allow() : Response::deny('Role aktif diperlukan untuk membaca katalog.');
    }

    public function write(User $user): Response
    {
        return $this->scope->isSuper($user) ? Response::allow() : Response::deny('Hanya SUPER_ADMIN dapat mengubah katalog.');
    }
}
