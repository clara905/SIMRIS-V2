<?php

use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\Role;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UserRoleScope;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    $this->withHeader('Origin', 'http://localhost:5173');
});

function satkerAdminFixture(): array
{
    $super = User::factory()->create();
    grantRole($super, 'SUPER_ADMIN');
    $root = UnitOrganisasi::factory()->create(['jenis' => 'SATKER']);
    $actorPegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $actorPegawai->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);
    $admin = User::factory()->create(['pegawai_id' => $actorPegawai->id]);
    grantRole($admin, 'ADMIN_SATKER', $root->id);
    foreach (['STAFF', 'KASUB', 'KABID', 'KASATKER', 'SUPER_ADMIN', 'ADMIN_SATKER'] as $code) {
        Role::firstOrCreate(['kode' => $code], ['nama' => $code]);
    }
    test()->actingAs($admin)->postJson('/api/konfirmasi-password', ['password' => 'password-testing'])->assertOk();

    return [$admin, $root];
}

function satkerUserPayload(string $username, int $pegawaiId): array
{
    return ['pegawai_id' => $pegawaiId, 'username' => $username, 'email_login' => $username.'@example.test', 'password' => 'Initial-password-123', 'password_confirmation' => 'Initial-password-123'];
}

test('admin satker creates scoped user and grants each allowed role with marked audit', function (string $code): void {
    [$admin, $root] = satkerAdminFixture();
    $pegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $pegawai->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);
    $userId = $this->actingAs($admin)->postJson('/api/users', satkerUserPayload('satker-'.$code, $pegawai->id))->assertCreated()->assertJsonPath('data.wajib_ganti_password', true)->json('data.id');
    $this->getJson('/api/users')->assertOk()->assertJsonFragment(['id' => $userId])->assertJsonFragment(['username' => 'satker-'.$code]);
    $role = Role::where('kode', $code)->firstOrFail();
    $scopeId = $this->postJson('/api/user-role-scope', ['user_id' => $userId, 'role_id' => $role->id, 'unit_id' => $root->id, 'mulai' => today()->toDateString()])->assertCreated()->json('data.id');
    $audit = DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $scopeId)->where('aksi', 'ROLE_CHANGE')->first();
    expect(json_decode($audit->data_baru, true))->toMatchArray(['oleh_admin_satker' => true, 'role' => $code]);
})->with(['STAFF', 'KASUB', 'KABID', 'KASATKER']);

test('admin satker cannot grant privileged roles or roles outside its unit scope', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $pegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $pegawai->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);
    $target = User::factory()->create(['pegawai_id' => $pegawai->id]);
    foreach (['ADMIN_SATKER', 'SUPER_ADMIN'] as $code) {
        $role = Role::firstOrCreate(['kode' => $code], ['nama' => $code]);
        $this->actingAs($admin)->postJson('/api/user-role-scope', ['user_id' => $target->id, 'role_id' => $role->id, 'unit_id' => $root->id, 'mulai' => today()->toDateString()])->assertConflict()->assertJsonPath('code', 'role_tidak_diizinkan');
    }
    $outside = UnitOrganisasi::factory()->create(['jenis' => 'SATKER']);
    $this->postJson('/api/user-role-scope', ['user_id' => $target->id, 'role_id' => Role::where('kode', 'STAFF')->value('id'), 'unit_id' => $outside->id, 'mulai' => today()->toDateString()])->assertConflict()->assertJsonPath('code', 'unit_di_luar_scope');
});

test('admin satker cannot manage self or privileged accounts', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $targets = [$admin];
    foreach (['SUPER_ADMIN', 'ADMIN_SATKER'] as $code) {
        $employee = Pegawai::factory()->create();
        PenempatanPegawai::factory()->create(['pegawai_id' => $employee->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);
        $privileged = User::factory()->create(['pegawai_id' => $employee->id]);
        grantRole($privileged, $code, $code === 'SUPER_ADMIN' ? null : $root->id);
        $targets[] = $privileged;
    }
    foreach ($targets as $target) {
        $this->actingAs($admin)->patchJson('/api/users/'.$target->id, ['aktif' => false])->assertConflict();
        $this->postJson('/api/users/'.$target->id.'/nonaktifkan')->assertConflict();
        $this->postJson('/api/users/'.$target->id.'/reset-password', ['password' => 'Reset-password-123', 'password_confirmation' => 'Reset-password-123'])->assertConflict();
    }
});

test('admin satker can create satker root and receives scope to add bidang and subbidang', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $newRoot = $this->actingAs($admin)->postJson('/api/unit-organisasi', [
        'parent_id' => null,
        'kode' => 'SATKER-ROOT-NEW',
        'nama' => 'Root baru',
        'jenis' => 'SATKER',
    ])->assertCreated()->assertJsonPath('data.parent_id', null)->json('data.id');
    $adminSatkerRole = Role::where('kode', 'ADMIN_SATKER')->firstOrFail();
    $scope = UserRoleScope::where('user_id', $admin->id)->where('role_id', $adminSatkerRole->id)->where('unit_id', $newRoot)->firstOrFail();
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'USER_ROLE_SCOPE', 'entitas_id' => $scope->id, 'aksi' => 'ROLE_CHANGE']);

    $bidang = $this->postJson('/api/unit-organisasi', [
        'parent_id' => $newRoot,
        'kode' => 'BIDANG-ROOT-NEW',
        'nama' => 'Bidang baru',
        'jenis' => 'BIDANG',
    ])->assertCreated()->assertJsonPath('data.parent_id', $newRoot)->json('data.id');
    $this->postJson('/api/unit-organisasi', [
        'parent_id' => $bidang,
        'kode' => 'SUBBIDANG-ROOT-NEW',
        'nama' => 'Subbidang baru',
        'jenis' => 'SUBBIDANG',
    ])->assertCreated()->assertJsonPath('data.parent_id', $bidang);

    $this->getJson('/api/unit-organisasi')->assertOk()->assertJsonFragment(['id' => $newRoot])->assertJsonFragment(['id' => $bidang]);
    $this->deleteJson('/api/unit-organisasi/'.$root->id)->assertConflict()->assertJsonPath('code', 'unit_induk_tidak_dapat_dinonaktifkan');
    $outside = UnitOrganisasi::factory()->create(['jenis' => 'SATKER']);
    $child = UnitOrganisasi::factory()->create(['parent_id' => $root->id, 'jenis' => 'BIDANG']);
    $this->postJson('/api/unit-organisasi/'.$child->id.'/pindah-induk', ['parent_id' => $outside->id])->assertConflict()->assertJsonPath('code', 'induk_di_luar_scope');
});

test('admin satker directory listings stay within active placement scope', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $insideEmployee = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $insideEmployee->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);
    $insideUser = User::factory()->create(['pegawai_id' => $insideEmployee->id]);
    grantRole($insideUser, 'STAFF', $root->id);
    $outsideEmployee = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $outsideEmployee->id, 'unit_id' => UnitOrganisasi::factory()->create(['jenis' => 'SATKER'])->id, 'mulai' => today()->subDay()]);
    $outsideUser = User::factory()->create(['pegawai_id' => $outsideEmployee->id]);
    grantRole($outsideUser, 'STAFF', UnitOrganisasi::factory()->create(['jenis' => 'SATKER'])->id);
    $this->actingAs($admin)->getJson('/api/users')->assertOk()->assertJsonFragment(['id' => $insideUser->id])->assertJsonMissing(['id' => $outsideUser->id]);
    $this->getJson('/api/user-role-scope')->assertOk()->assertJsonFragment(['user_id' => $insideUser->id])->assertJsonMissing(['user_id' => $outsideUser->id]);
    $this->getJson('/api/roles')->assertOk()->assertJsonMissing(['kode' => 'ADMIN_SATKER'])->assertJsonMissing(['kode' => 'SUPER_ADMIN']);
});

test('admin satker and other operational roles cannot write super admin only administration', function (string $role): void {
    $unit = UnitOrganisasi::factory()->create(['jenis' => 'SATKER']);
    $user = User::factory()->create();
    grantRole($user, $role, $unit->id);
    $this->actingAs($user)->postJson('/api/periode-penilaian', [])->assertForbidden();
    $this->postJson('/api/rencana-kerja', [])->assertForbidden();
})->with(['ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER']);

test('super admin availability guard still blocks writes when no active super admin remains', function (): void {
    [$admin, $root] = satkerAdminFixture();
    User::where('id', '!=', $admin->id)->update(['aktif' => false]);
    $pegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $pegawai->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);
    $this->actingAs($admin)->postJson('/api/users', satkerUserPayload('no-super-write', $pegawai->id))->assertConflict()->assertJsonPath('code', 'super_admin_terakhir');
    $this->assertDatabaseMissing('USERS', ['username' => 'no-super-write']);
});
