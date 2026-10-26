<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ScopeUnit;
use Illuminate\Auth\Access\Response;

class MetodeRisikoPolicy
{
    public function __construct(private ScopeUnit $scope) {}

    public function view(User $user): Response
    {
        return $user->aktif && ($this->scope->isSuper($user) || $this->scope->hasRole($user, ['ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF'])) ? Response::allow() : Response::deny('Role aktif Anda tidak berhak membaca metode risiko.');
    }

    public function write(User $user): Response
    {
        return $this->scope->isSuper($user) ? Response::allow() : Response::deny('Hanya SUPER_ADMIN aktif yang dapat mengubah metode risiko.');
    }
}
