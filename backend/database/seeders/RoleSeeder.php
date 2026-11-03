<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::unguarded(function () {
            foreach ([
                'SUPER_ADMIN' => 'Super Admin',
                'ADMIN_SATKER' => 'Admin Satker',
                'KASATKER' => 'Kepala Satker',
                'KABID' => 'Kepala Bidang',
                'KASUB' => 'Kepala Sub Bidang',
                'STAFF' => 'Staf',
            ] as $kode => $nama) {
                Role::query()->updateOrCreate(['kode' => $kode], ['nama' => $nama]);
            }
        });
    }
}
