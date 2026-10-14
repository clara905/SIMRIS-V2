<?php

namespace App\Policies;

use App\Models\User;
use App\Services\ScopeUnit;
use Illuminate\Auth\Access\Response;

class KepatuhanPolicy
{
    public function __construct(private ScopeUnit $scope) {}

    public function write(User $actor, int $unit, bool $adminOnly = false): Response
    {
        return in_array($unit, $this->scope->idsForRoles($actor, $adminOnly ? ['ADMIN_SATKER'] : ['ADMIN_SATKER', 'STAFF']), true) ? Response::allow() : Response::deny('Hanya penyusun dalam scope dapat mengubah draft; pengajuan dan penghapusan memerlukan ADMIN_SATKER.');
    }
}
