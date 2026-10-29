<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ScopeUnit;
use Illuminate\Auth\Access\Response;

class KepatuhanPolicy
{
    public function __construct(private ScopeUnit $scope) {}

    public function write(User $actor, int $unit, bool $adminOnly = false, ?string $resource = null): Response
    {
        $staffRestricted = in_array($resource, ['kontrol-internal', 'sop', 'sop-versi'], true);
        $roles = $adminOnly || $staffRestricted ? ['ADMIN_SATKER'] : ['ADMIN_SATKER', 'STAFF'];

        return in_array($unit, $this->scope->idsForRoles($actor, $roles), true) ? Response::allow() : Response::deny('Hanya penyusun dalam scope dapat mengubah draft; pengajuan dan penghapusan memerlukan ADMIN_SATKER.');
    }

    public function reviewSop(User $actor): Response
    {
        return $this->scope->isSuper($actor)
            ? Response::allow()
            : Response::deny('Hanya SUPER_ADMIN yang dapat meninjau dan mengesahkan SOP.');
    }
}
