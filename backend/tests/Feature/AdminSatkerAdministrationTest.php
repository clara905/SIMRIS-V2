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

function satkerAdminSession(User $user): mixed
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
}

function satkerAdminFixture(): array
{
    $superPegawai = Pegawai::factory()->create();
    $super = User::factory()->create(['pegawai_id' => $superPegawai->id]);
    grantRole($super, 'SUPER_ADMIN');

    $root = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);
    $actorPegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create([
        'pegawai_id' => $actorPegawai->id,
        'unit_id' => $root->id,
        'mulai' => today()->subDay(),
        'selesai' => null,
    ]);
    $admin = User::factory()->create(['pegawai_id' => $actorPegawai->id]);
    grantRole($admin, 'ADMIN_SATKER', $root->id);

    foreach (['STAFF', 'KASUB', 'KABID', 'KASATKER', 'SUPER_ADMIN', 'ADMIN_SATKER'] as $code) {
        Role::firstOrCreate(['kode' => $code], ['nama' => $code]);
    }

    return [$admin, $root, $super];
}

function satkerUserPayload(string $username, int $pegawaiId): array
{
    return [
        'pegawai_id' => $pegawaiId,
        'username' => $username,
        'email_login' => $username.'@example.test',
        'password' => 'Initial-password-123',
        'password_confirmation' => 'Initial-password-123',
    ];
}

test('admin satker creates scoped user and grants each allowed role with marked audit', function (string $code): void {
    [$admin, $root] = satkerAdminFixture();
    $pegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $pegawai->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);

    $userId = satkerAdminSession($admin)->postJson('/api/users', satkerUserPayload('satker-'.$code, $pegawai->id))
        ->assertCreated()
        ->assertJsonPath('data.wajib_ganti_password', true)
        ->json('data.id');

    satkerAdminSession($admin)->getJson('/api/users')->assertOk()->assertJsonFragment(['id' => $userId])->assertJsonFragment(['username' => 'satker-'.$code]);

    $role = Role::where('kode', $code)->firstOrFail();
    $scopeId = satkerAdminSession($admin)->postJson('/api/user-role-scope', [
        'user_id' => $userId,
        'role_id' => $role->id,
        'unit_id' => $root->id,
        'mulai' => today()->toDateString(),
    ])->assertCreated()->json('data.id');

    $audit = DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $scopeId)->where('aksi', 'ROLE_CHANGE')->first();
    $expectedAudit = ['oleh_admin_satker' => true, 'role' => $code];
    if ($code === 'KASATKER') {
        $expectedAudit['perubahan_kasatker'] = true;
    }
    expect(json_decode($audit->data_baru, true))->toMatchArray($expectedAudit);
})->with(['STAFF', 'KASUB', 'KABID', 'KASATKER']);

test('admin satker cannot grant privileged roles or roles outside its unit scope', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $pegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $pegawai->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);
    $target = User::factory()->create(['pegawai_id' => $pegawai->id]);

    foreach (['ADMIN_SATKER', 'SUPER_ADMIN'] as $code) {
        $role = Role::firstOrCreate(['kode' => $code], ['nama' => $code]);
        satkerAdminSession($admin)->postJson('/api/user-role-scope', [
            'user_id' => $target->id,
            'role_id' => $role->id,
            'unit_id' => $root->id,
            'mulai' => today()->toDateString(),
        ])->assertConflict()->assertJsonPath('code', 'role_tidak_diizinkan');
    }

    $outside = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);
    satkerAdminSession($admin)->postJson('/api/user-role-scope', [
        'user_id' => $target->id,
        'role_id' => Role::where('kode', 'STAFF')->value('id'),
        'unit_id' => $outside->id,
        'mulai' => today()->toDateString(),
    ])->assertConflict()->assertJsonPath('code', 'unit_di_luar_scope');
});

test('admin satker cannot manage self or privileged accounts', function (): void {
    [$admin, $root, $super] = satkerAdminFixture();

    // 1. Akun sendiri
    satkerAdminSession($admin)->patchJson('/api/users/'.$admin->id, ['aktif' => false])
        ->assertConflict()->assertJsonPath('code', 'akun_sendiri_dilindungi');
    satkerAdminSession($admin)->postJson('/api/users/'.$admin->id.'/nonaktifkan')
        ->assertConflict()->assertJsonPath('code', 'akun_sendiri_dilindungi');
    satkerAdminSession($admin)->postJson('/api/users/'.$admin->id.'/reset-password', [
        'password' => 'Reset-password-123',
        'password_confirmation' => 'Reset-password-123',
    ])->assertConflict()->assertJsonPath('code', 'akun_sendiri_dilindungi');

    // 2. Akun Super Admin yang memiliki penempatan di wilayah satker (terlihat tetapi dilindungi)
    PenempatanPegawai::factory()->create([
        'pegawai_id' => $super->pegawai_id,
        'unit_id' => $root->id,
        'mulai' => today()->subDay(),
    ]);
    satkerAdminSession($admin)->patchJson('/api/users/'.$super->id, ['aktif' => false])
        ->assertConflict()->assertJsonPath('code', 'user_dilindungi');
    satkerAdminSession($admin)->postJson('/api/users/'.$super->id.'/nonaktifkan')
        ->assertConflict()->assertJsonPath('code', 'user_dilindungi');
    satkerAdminSession($admin)->postJson('/api/users/'.$super->id.'/reset-password', [
        'password' => 'Reset-password-123',
        'password_confirmation' => 'Reset-password-123',
    ])->assertConflict()->assertJsonPath('code', 'user_dilindungi');

    // 3. Akun Admin Satker lain (berada di luar wilayah -> 404)
    $otherRoot = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);
    $otherEmp = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $otherEmp->id, 'unit_id' => $otherRoot->id, 'mulai' => today()->subDay()]);
    $otherAdmin = User::factory()->create(['pegawai_id' => $otherEmp->id]);
    grantRole($otherAdmin, 'ADMIN_SATKER', $otherRoot->id);

    satkerAdminSession($admin)->patchJson('/api/users/'.$otherAdmin->id, ['aktif' => false])->assertNotFound();
    satkerAdminSession($admin)->postJson('/api/users/'.$otherAdmin->id.'/nonaktifkan')->assertNotFound();
    satkerAdminSession($admin)->postJson('/api/users/'.$otherAdmin->id.'/reset-password', [
        'password' => 'Reset-password-123',
        'password_confirmation' => 'Reset-password-123',
    ])->assertNotFound();
});

test('admin satker can manage child units in scope but cannot manage root or move outside scope', function (): void {
    [$admin, $root] = satkerAdminFixture();

    // 1. Ditolak membuat unit akar / berjenis SATKER
    satkerAdminSession($admin)->postJson('/api/unit-organisasi', [
        'parent_id' => null,
        'kode' => 'SATKER-ROOT-NEW',
        'nama' => 'Root baru',
        'jenis' => 'SATKER',
    ])->assertConflict()->assertJsonPath('code', 'induk_di_luar_scope');

    // 2. Berhasil membuat unit anak (BIDANG) di bawah satkernya
    $bidang = satkerAdminSession($admin)->postJson('/api/unit-organisasi', [
        'parent_id' => $root->id,
        'kode' => 'BID-01',
        'nama' => 'Bidang Pengujian',
        'jenis' => 'BIDANG',
    ])->assertCreated()->json('data');

    // 3. Berhasil mengubah unit anak di wilayahnya
    satkerAdminSession($admin)->patchJson('/api/unit-organisasi/'.$bidang['id'], [
        'nama' => 'Bidang Pengujian Diperbarui',
    ])->assertOk()->assertJsonPath('data.nama', 'Bidang Pengujian Diperbarui');

    // 4. Ditolak mengubah atau menonaktifkan unit SATKER akar miliknya
    satkerAdminSession($admin)->patchJson('/api/unit-organisasi/'.$root->id, ['nama' => 'Ubah root'])
        ->assertConflict()->assertJsonPath('code', 'unit_satker_dilindungi');
    satkerAdminSession($admin)->patchJson('/api/unit-organisasi/'.$root->id, ['aktif' => false])
        ->assertConflict()->assertJsonPath('code', 'unit_satker_dilindungi');
    satkerAdminSession($admin)->deleteJson('/api/unit-organisasi/'.$root->id)
        ->assertConflict()->assertJsonPath('code', 'unit_satker_dilindungi');

    // 5. Ditolak memindahkan unit anak ke luar wilayah atau ke null
    $otherRoot = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);
    $resNull = satkerAdminSession($admin)->postJson('/api/unit-organisasi/'.$bidang['id'].'/pindah-induk', ['parent_id' => null]);
    $resNull->assertConflict();
    expect(in_array($resNull->json('code'), ['induk_di_luar_scope', 'level_unit_tidak_valid'], true))->toBeTrue();

    $resOther = satkerAdminSession($admin)->postJson('/api/unit-organisasi/'.$bidang['id'].'/pindah-induk', ['parent_id' => $otherRoot->id]);
    $resOther->assertConflict()->assertJsonPath('code', 'induk_di_luar_scope');

    // 6. Pindah induk ke induk yang sah di dalam wilayahnya berhasil
    $bagian = satkerAdminSession($admin)->postJson('/api/unit-organisasi', [
        'parent_id' => $root->id,
        'kode' => 'BAG-01',
        'nama' => 'Bagian Umum',
        'jenis' => 'BAGIAN',
    ])->assertCreated()->json('data');

    $subbagian = satkerAdminSession($admin)->postJson('/api/unit-organisasi', [
        'parent_id' => $bagian['id'],
        'kode' => 'SUBBAG-01',
        'nama' => 'Subbagian Tata Usaha',
        'jenis' => 'SUBBAGIAN',
    ])->assertCreated()->json('data');

    $bagian2 = satkerAdminSession($admin)->postJson('/api/unit-organisasi', [
        'parent_id' => $root->id,
        'kode' => 'BAG-02',
        'nama' => 'Bagian Keuangan',
        'jenis' => 'BAGIAN',
    ])->assertCreated()->json('data');

    satkerAdminSession($admin)->postJson('/api/unit-organisasi/'.$subbagian['id'].'/pindah-induk', ['parent_id' => $bagian2['id']])
        ->assertOk()->assertJsonPath('data.parent_id', $bagian2['id']);
});

test('admin satker cannot place employee outside scope and cannot mutate employee from other satker', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $otherRoot = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);

    // Pegawai baru di satker ini
    $localPegawai = Pegawai::factory()->create();

    // 1. Ditolak menempatkan ke unit di luar wilayahnya
    satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $localPegawai->id,
        'unit_id' => $otherRoot->id,
        'mulai' => today()->toDateString(),
    ])->assertConflict()->assertJsonPath('code', 'unit_di_luar_scope');

    // 2. Berhasil menempatkan ke unit di wilayahnya
    $penempatan = satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $localPegawai->id,
        'unit_id' => $root->id,
        'mulai' => today()->toDateString(),
    ])->assertCreated()->json('data');

    // 3. Pegawai yang memiliki penempatan aktif di satker lain tidak boleh dimutasi oleh admin satker
    $foreignPegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create([
        'pegawai_id' => $foreignPegawai->id,
        'unit_id' => $otherRoot->id,
        'mulai' => today()->subMonth(),
        'selesai' => null,
    ]);

    satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $foreignPegawai->id,
        'unit_id' => $root->id,
        'mulai' => today()->toDateString(),
    ])->assertConflict()->assertJsonPath('code', 'mutasi_lintas_satker_dilarang');

    // 4. Mengakhiri penempatan di wilayahnya berhasil
    satkerAdminSession($admin)->patchJson('/api/penempatan-pegawai/'.$penempatan['id'], [
        'selesai' => today()->toDateString(),
    ])->assertOk()->assertJsonPath('data.selesai', today()->toDateString());
});

test('duplicate employee NIP validation message does not leak other satker information', function (): void {
    [$admin, $root] = satkerAdminFixture();
    Pegawai::factory()->create(['nip' => '199001012015011001']);

    satkerAdminSession($admin)->postJson('/api/pegawai', [
        'nip' => '199001012015011001',
        'nama' => 'Pegawai Duplikat NIP',
        'email_kontak' => 'duplikat@example.test',
        'jenis_kepegawaian' => 'PNS',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['nip'])
        ->assertJsonPath('errors.nip.0', 'NIP sudah terdaftar. Hubungi super admin untuk proses mutasi.');
});

test('super admin manages ADMIN_SATKER with single active constraint and clean handover', function (): void {
    [$admin, $satkerA, $super] = satkerAdminFixture();
    $satkerB = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);
    $subUnit = UnitOrganisasi::factory()->create(['jenis' => 'BIDANG', 'parent_id' => $satkerA->id]);

    $adminSatkerRole = Role::where('kode', 'ADMIN_SATKER')->firstOrFail();

    $newEmployee = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $newEmployee->id, 'unit_id' => $satkerA->id, 'mulai' => today()->subDay()]);
    $newUser = User::factory()->create(['pegawai_id' => $newEmployee->id]);

    // 1. ADMIN_SATKER pada unit non-SATKER -> ditolak
    satkerAdminSession($super)->postJson('/api/user-role-scope', [
        'user_id' => $newUser->id,
        'role_id' => $adminSatkerRole->id,
        'unit_id' => $subUnit->id,
        'mulai' => today()->toDateString(),
    ])->assertConflict()->assertJsonPath('code', 'admin_satker_harus_satker_akar');

    // 2. ADMIN_SATKER pada satker yang sudah memiliki admin aktif -> ditolak ('admin_satker_ganda')
    satkerAdminSession($super)->postJson('/api/user-role-scope', [
        'user_id' => $newUser->id,
        'role_id' => $adminSatkerRole->id,
        'unit_id' => $satkerA->id,
        'mulai' => today()->toDateString(),
    ])->assertConflict()->assertJsonPath('code', 'admin_satker_ganda');

    // 3. Satu akun memegang ADMIN_SATKER aktif pada dua satker berbeda -> ditolak
    satkerAdminSession($super)->postJson('/api/user-role-scope', [
        'user_id' => $admin->id,
        'role_id' => $adminSatkerRole->id,
        'unit_id' => $satkerB->id,
        'mulai' => today()->toDateString(),
    ])->assertConflict()->assertJsonPath('code', 'admin_satker_satu_satker');

    // 4. Penggantian admin: akhiri yang lama lalu pasang yang baru -> berhasil
    $oldScope = UserRoleScope::where('user_id', $admin->id)->where('role_id', $adminSatkerRole->id)->where('unit_id', $satkerA->id)->firstOrFail();
    $selesaiLama = now()->subHour()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
    satkerAdminSession($super)->patchJson('/api/user-role-scope/'.$oldScope->id, [
        'selesai' => $selesaiLama,
    ])->assertOk();

    $newScope = satkerAdminSession($super)->postJson('/api/user-role-scope', [
        'user_id' => $newUser->id,
        'role_id' => $adminSatkerRole->id,
        'unit_id' => $satkerA->id,
        'mulai' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
    ])->assertCreated()->json('data');

    expect($newScope['unit_id'])->toBe($satkerA->id);
});

test('operational roles STAFF, KASUB, KABID, KASATKER receive 403 on administration write', function (string $role): void {
    $satker = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);
    $pegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $pegawai->id, 'unit_id' => $satker->id, 'mulai' => today()->subDay()]);
    $user = User::factory()->create(['pegawai_id' => $pegawai->id]);
    grantRole($user, $role, $satker->id);

    satkerAdminSession($user)->postJson('/api/users', satkerUserPayload('op-test', $pegawai->id))->assertForbidden();
    satkerAdminSession($user)->postJson('/api/unit-organisasi', [
        'parent_id' => $satker->id,
        'kode' => 'OP-UNIT',
        'nama' => 'Unit Op',
        'jenis' => 'BIDANG',
    ])->assertForbidden();
    satkerAdminSession($user)->postJson('/api/pegawai', [
        'nama' => 'Pegawai Op',
        'email_kontak' => 'op@example.test',
        'jenis_kepegawaian' => 'PNS',
    ])->assertForbidden();
    satkerAdminSession($user)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $pegawai->id,
        'unit_id' => $satker->id,
        'mulai' => today()->toDateString(),
    ])->assertForbidden();
})->with(['STAFF', 'KASUB', 'KABID', 'KASATKER']);

test('super admin regression and super_admin_terakhir guard remain active', function (): void {
    [$admin, $root, $super] = satkerAdminFixture();

    // Super admin dapat mengelola master administrasi
    $periode = satkerAdminSession($super)->postJson('/api/periode-penilaian', [
        'nama' => 'Periode 2027',
        'tanggal_mulai' => '2027-01-01',
        'tanggal_selesai' => '2027-12-31',
    ])->assertCreated()->json('data');
    expect($periode['nama'])->toBe('Periode 2027');

    // Menjaga minimal satu super admin aktif
    $superScope = UserRoleScope::where('user_id', $super->id)->whereNull('unit_id')->firstOrFail();
    satkerAdminSession($super)->deleteJson('/api/user-role-scope/'.$superScope->id)->assertConflict()->assertJsonPath('code', 'tidak_boleh_cabut_role_sendiri');
});
