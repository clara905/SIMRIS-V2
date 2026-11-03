<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuatSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function jumlahSuperAdmin(): int
    {
        $roleId = Role::query()->where('kode', 'SUPER_ADMIN')->value('id');

        return UserRoleScope::query()->where('role_id', $roleId)->count();
    }

    public function test_gagal_bila_role_belum_di_seed(): void
    {
        $this->artisan('simris:buat-super-admin', ['email' => 'admin@simris.test'])->expectsQuestion('Kata sandi (minimal 8 karakter)', 'LocalPass#123')->expectsQuestion('Ulangi kata sandi', 'LocalPass#123')->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_membuat_super_admin_tanpa_unit(): void
    {
        $this->seed(RoleSeeder::class);

        $this->artisan('simris:buat-super-admin', ['email' => 'Admin@Simris.Test'])->expectsQuestion('Kata sandi (minimal 8 karakter)', 'LocalPass#123')->expectsQuestion('Ulangi kata sandi', 'LocalPass#123')->assertSuccessful();

        $user = User::query()->where('email_login', 'admin@simris.test')->firstOrFail();
        $this->assertNull($user->pegawai_id);
        $this->assertTrue($user->aktif);
        $this->assertNull($user->penugasan()->firstOrFail()->unit_id);
        $this->assertSame(1, $this->jumlahSuperAdmin());
    }

    public function test_super_admin_kedua_tidak_bisa_dibuat(): void
    {
        $this->seed(RoleSeeder::class);
        $this->artisan('simris:buat-super-admin', ['email' => 'admin@simris.test'])->expectsQuestion('Kata sandi (minimal 8 karakter)', 'LocalPass#123')->expectsQuestion('Ulangi kata sandi', 'LocalPass#123')->assertSuccessful();

        $this->artisan('simris:buat-super-admin', ['email' => 'lain@simris.test', '--username' => 'lain'])->expectsQuestion('Kata sandi (minimal 8 karakter)', 'LocalPass#123')->expectsQuestion('Ulangi kata sandi', 'LocalPass#123')->assertFailed();

        $this->assertSame(1, $this->jumlahSuperAdmin());
        $this->assertSame(1, User::query()->count());
    }

    public function test_email_tidak_valid_ditolak(): void
    {
        $this->seed(RoleSeeder::class);

        $this->artisan('simris:buat-super-admin', ['email' => 'bukan-email'])->expectsQuestion('Kata sandi (minimal 8 karakter)', 'LocalPass#123')->expectsQuestion('Ulangi kata sandi', 'LocalPass#123')->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
