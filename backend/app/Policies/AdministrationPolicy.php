<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\ScopeUnit;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class AdministrationPolicy
{
    public function __construct(private ScopeUnit $scope) {}

    public function viewAny(User $user, string $class): Response
    {
        $allowed = $this->scope->isSuper($user) || (! in_array($class, [User::class, UserRoleScope::class], true) && $this->scope->hasRole($user, ['ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']));

        return $allowed ? Response::allow() : Response::deny('Role aktif Anda tidak berhak membaca data administrasi ini.');
    }

    public function create(User $user, string $class): Response
    {
        return $this->write($user, $class);
    }

    public function update(User $user, Model $model): Response
    {
        return $this->write($user, $model::class);
    }

    public function delete(User $user, Model $model): Response
    {
        return $this->write($user, $model::class);
    }

    private function write(User $user, string $class): Response
    {
        return $class !== Role::class && $this->scope->isSuper($user) ? Response::allow() : Response::deny('Hanya SUPER_ADMIN aktif yang dapat mengubah data administrasi.');
    }
}
