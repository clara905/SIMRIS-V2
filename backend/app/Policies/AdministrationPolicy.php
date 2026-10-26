<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\UnitOrganisasi;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
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
        $satkerReadable = [User::class, UserRoleScope::class, Role::class, UnitOrganisasi::class, Pegawai::class, PenempatanPegawai::class];
        $allowed = $this->scope->isSuper($user) || ($this->scope->hasRole($user, ['ADMIN_SATKER'])
            ? in_array($class, $satkerReadable, true)
            : (! in_array($class, [User::class, UserRoleScope::class, Role::class], true) && $this->scope->hasRole($user, ['KASATKER', 'KABID', 'KASUB', 'STAFF'])));

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
        if ($this->scope->isSuper($user)) {
            return $class !== Role::class ? Response::allow() : Response::deny('Role hanya dikelola oleh SUPER_ADMIN.');
        }
        $satkerWritable = [User::class, UserRoleScope::class, UnitOrganisasi::class, Pegawai::class, PenempatanPegawai::class];

        return $this->scope->hasRole($user, ['ADMIN_SATKER']) && in_array($class, $satkerWritable, true)
            ? Response::allow()
            : Response::deny('Hanya SUPER_ADMIN atau ADMIN_SATKER dalam scope yang dapat mengubah data administrasi ini.');
    }
}
