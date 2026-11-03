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
            $ids = $this->isSuper($user)
                ? $units->pluck('id')->map(fn ($id) => (int) $id)->all()
                : $this->effectiveRoleUnits($user, $baseRoles, $units);
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
        $units = DB::table('UNIT_ORGANISASI')->get(['id', 'parent_id']);
        $ids = $this->effectiveRoleUnits($user, $this->roles($user)->whereIn('kode', $roles), $units);
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

    /**
     * Intersect role grants with the employee's current primary placement.
     * A move into an unrelated unit can therefore never retain access to the
     * former unit or grant access to the new one without a matching role scope.
     *
     * @param  Collection<int, object>  $roles
     * @param  Collection<int, object>  $units
     * @return list<int>
     */
    private function effectiveRoleUnits(User $user, Collection $roles, Collection $units): array
    {
        if (! $user->pegawai_id) {
            return [];
        }

        $placementUnit = DB::table('PENEMPATAN_PEGAWAI')
            ->where('pegawai_id', $user->pegawai_id)
            ->where('utama', true)
            ->where('mulai', '<=', today())
            ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()))
            ->value('unit_id');
        if ($placementUnit === null) {
            return [];
        }

        $parents = $units->mapWithKeys(fn ($unit) => [(int) $unit->id => $unit->parent_id === null ? null : (int) $unit->parent_id]);
        $isAncestor = function (int $ancestor, int $unitId) use ($parents): bool {
            $visited = [];
            while (isset($parents[$unitId]) && $parents[$unitId] !== null && ! isset($visited[$unitId])) {
                $parent = (int) $parents[$unitId];
                if ($parent === $ancestor) {
                    return true;
                }
                $visited[$unitId] = true;
                $unitId = $parent;
            }

            return false;
        };

        $placementUnit = (int) $placementUnit;
        $effective = [];
        foreach ($roles->whereNotNull('unit_id') as $role) {
            $scopeUnit = (int) $role->unit_id;
            if ($role->kode === 'ADMIN_SATKER') {
                // ADMIN_SATKER is granted at the SATKER root and owns its full
                // descendant tree, even when the person's own post is deeper.
                if ($scopeUnit === $placementUnit || $isAncestor($scopeUnit, $placementUnit)) {
                    $effective[] = $scopeUnit;
                }

                continue;
            }
            if ($scopeUnit === $placementUnit || $isAncestor($placementUnit, $scopeUnit)) {
                $effective[] = $scopeUnit;
            } elseif ($isAncestor($scopeUnit, $placementUnit)) {
                // A broad role on a parent is narrowed to the employee's placement.
                $effective[] = $placementUnit;
            }
        }

        return array_values(array_unique($effective));
    }

    public function forget(User $user): void
    {
        request()->attributes->remove('simris.scope.'.$user->id);
    }
}
