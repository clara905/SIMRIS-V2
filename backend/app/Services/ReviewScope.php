<?php

namespace App\Services;

use App\Models\UnitOrganisasi;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ReviewScope
{
    public function __construct(private ScopeUnit $scope) {}

    public function authorize(User $actor, int $unitId, string $role): void
    {
        $units = [];
        $unit = UnitOrganisasi::findOrFail($unitId);
        do {
            if (isset($units[$unit->id])) {
                break;
            } $units[$unit->id] = $unit->jenis;
            $unit = $unit->parent_id ? UnitOrganisasi::findOrFail($unit->parent_id) : null;
        } while ($unit);
        $allowed = $actor->aktif && $this->scope->roles($actor)->contains(fn ($grant): bool => $grant->kode === $role && isset($units[$grant->unit_id]) && in_array($units[$grant->unit_id], config('alur_review.jenis_role.'.$role, []), true));
        if (! $allowed) {
            throw new AuthorizationException('Reviewer harus memegang role '.$role.' pada tingkat dan jalur unit asesmen yang sesuai.');
        }
    }
}
