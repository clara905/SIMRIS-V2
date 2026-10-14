<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UserRoleScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserRoleScope> */
class UserRoleScopeFactory extends Factory
{
    protected $model = UserRoleScope::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'role_id' => Role::factory(), 'unit_id' => UnitOrganisasi::factory(), 'mulai' => now(), 'selesai' => null];
    }
}
