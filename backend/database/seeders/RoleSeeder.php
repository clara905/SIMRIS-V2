<?php

namespace Database\Seeders;

use App\Enums\RoleKode;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::unguarded(function () {
            foreach (RoleKode::cases() as $role) {
                Role::query()->updateOrCreate(['kode' => $role->value], ['nama' => $role->nama()]);
            }
        });
    }
}