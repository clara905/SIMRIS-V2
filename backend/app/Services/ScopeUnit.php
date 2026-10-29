<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScopeUnit
{
    public function roles(User $user): Collection
    {
        return DB::table('USER_ROLE_SCOPE as s')->join('ROLES as r', 'r.id', '=', 's.role_id')->where('s.user_id', $user->id)->where('s.mulai', '<=', now())->where(fn ($q) => $q->whereNull('s.selesai')->orWhere('s.selesai', '>=', now()))->select('s.*', 'r.kode', 'r.nama')->get();
    }

    public function isSuper(User $user): bool
    {
        return $user->aktif && $this->roles($user)->contains(fn ($r) => $r->kode === 'SUPER_ADMIN' && $r->unit_id === null);
    }

    public function hasRole(User $user, array $codes): bool
    {
        return $user->aktif && $this->roles($user)->contains(fn ($r) => in_array($r->kode, $codes, true));
    }

    /** @return list<int> */
    public function ids(User $user): array
    {
        $key = 'simris.scope.'.$user->id;
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }
        $ids = [];
        if ($user->aktif) {
            $units = DB::table('UNIT_ORGANISASI')->get(['id', 'parent_id']);
            $activeRoles = $this->roles($user);
            $baseRoles = $this->isSuper($user)
                ? $activeRoles
                : ($activeRoles->contains(fn ($role) => $role->kode === 'ADMIN_SATKER')
                    ? $activeRoles->where('kode', 'ADMIN_SATKER')
                    : $activeRoles);
            $ids = $this->isSuper($user) ? $units->pluck('id')->map(fn ($id) => (int) $id)->all() : $baseRoles->whereNotNull('unit_id')->pluck('unit_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
            do {
                $before = count($ids);
                foreach ($units as $unit) {
                    if (in_array((int) $unit->parent_id, $ids, true) && ! in_array((int) $unit->id, $ids, true)) {
                        $ids[] = (int) $unit->id;
                    }
                }
            } while (count($ids) !== $before);
        }
        request()->attributes->set($key, $ids);

        return $ids;
    }

    /** Unit coverage from the required role only, not the union of unrelated roles. */
    public function idsForRoles(User $user, array $roles): array
    {
        if (! $user->aktif) {
            return [];
        }
        $ids = $this->roles($user)->whereIn('kode', $roles)->whereNotNull('unit_id')->pluck('unit_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $units = DB::table('UNIT_ORGANISASI')->get(['id', 'parent_id']);
        do {
            $count = count($ids);
            foreach ($units as $unit) {
                if (in_array((int) $unit->parent_id, $ids, true) && ! in_array((int) $unit->id, $ids, true)) {
                    $ids[] = (int) $unit->id;
                }
            }
        } while ($count !== count($ids));

        return $ids;
    }

    public function forget(User $user): void
    {
        request()->attributes->remove('simris.scope.'.$user->id);
    }
}
