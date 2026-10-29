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

test('admin satker may reveal NIP only for in-scope employees after confirming its own password', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $employee = Pegawai::factory()->create(['nip' => '123456789012345678']);
    PenempatanPegawai::factory()->create([
        'pegawai_id' => $employee->id,
        'unit_id' => $root->id,
        'mulai' => today()->subDay(),
    ]);
    $otherRoot = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);
    $otherEmployee = Pegawai::factory()->create(['nip' => '987654321098765432']);
    PenempatanPegawai::factory()->create([
        'pegawai_id' => $otherEmployee->id,
        'unit_id' => $otherRoot->id,
        'mulai' => today()->subDay(),
    ]);
    $this->actingAs($admin)->postJson('/api/pegawai/'.$employee->id.'/lihat-nip')
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    satkerAdminSession($admin)->getJson('/api/pegawai?filter[nama]='.$employee->nama)
        ->assertOk()->assertJsonPath('data.0.nip', str_repeat('•', 18));
    satkerAdminSession($admin)->postJson('/api/pegawai/'.$otherEmployee->id.'/lihat-nip')
        ->assertNotFound();

    $this->postJson('/api/pegawai/'.$employee->id.'/lihat-nip', ['password' => 'password-testing'])
        ->assertOk()->assertJsonPath('data.nip', '123456789012345678');
});

test('admin satker can permanently delete a scoped user and the role scope is audited', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $employee = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create([
        'pegawai_id' => $employee->id,
        'unit_id' => $root->id,
        'mulai' => today()->subDay(),
    ]);
    $user = User::factory()->create(['pegawai_id' => $employee->id]);
    $scope = UserRoleScope::factory()->create([
        'user_id' => $user->id,
        'role_id' => Role::where('kode', 'STAFF')->value('id'),
        'unit_id' => $root->id,
    ]);

    satkerAdminSession($admin)->deleteJson('/api/users/'.$user->id)->assertOk();

    $this->assertDatabaseMissing('USERS', ['id' => $user->id]);
    $this->assertDatabaseMissing('USER_ROLE_SCOPE', ['id' => $scope->id]);
    $this->assertDatabaseHas('LOG_AUDIT', [
        'entitas' => 'USER_ROLE_SCOPE',
        'entitas_id' => $scope->id,
        'aksi' => 'ROLE_CHANGE',
    ]);
});

test('admin satker creates scoped user and grants each allowed role with marked audit', function (string $code): void {
    [$admin, $root] = satkerAdminFixture();
    $pegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $pegawai->id, 'unit_id' => $root->id, 'mulai' => today()->subDay()]);

    $userId = satkerAdminSession($admin)->postJson('/api/users', satkerUserPayload('satker-'.$code, $pegawai->id))
        ->assertCreated()
        ->assertJsonPath('data.wajib_ganti_password', true)
        ->json('data.id');

    satkerAdminSession($admin)->getJson('/api/users?filter[pegawai_id]='.$pegawai->id)
        ->assertOk()
        ->assertJsonFragment(['id' => $userId])
        ->assertJsonFragment(['username' => 'satker-'.$code])
        ->assertJsonPath('data.0.penempatan_aktif.0.unit_id', $root->id);

    $role = Role::where('kode', $code)->firstOrFail();
    $scopeId = satkerAdminSession($admin)->postJson('/api/user-role-scope', [
        'user_id' => $userId,
        'role_id' => $role->id,
        'unit_id' => $root->id,
        'mulai' => today()->toDateString(),
    ])->assertCreated()->json('data.id');

    $audit = DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $scopeId)->where('aksi', 'ROLE_CHANGE')->first();
    expect(DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $scopeId)->count())->toBe(1);
    $expectedAudit = ['oleh_admin_satker' => true, 'peran' => $code];
    if ($code === 'KASATKER') {
        $expectedAudit['penanda'] = 'KASATKER_DIBERIKAN';
    }
    expect(json_decode($audit->data_baru, true))->toMatchArray($expectedAudit);
    if ($code === 'KASATKER') {
        satkerAdminSession($admin)->deleteJson('/api/user-role-scope/'.$scopeId)->assertOk();
        $revokeAudit = DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $scopeId)->where('aksi', 'ROLE_CHANGE')->orderByDesc('id')->first();
        expect(json_decode($revokeAudit->data_baru, true))->toMatchArray([
            'oleh_admin_satker' => true,
            'peran' => 'KASATKER',
            'penanda' => 'KASATKER_DICABUT',
        ]);
        expect(DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $scopeId)->count())->toBe(2);
    }
    satkerAdminSession($admin)->postJson('/api/users/'.$userId.'/reset-password', [
        'password' => 'Reset-password-123',
        'password_confirmation' => 'Reset-password-123',
    ])->assertOk();
    $passwordAudit = DB::table('LOG_AUDIT')->where('entitas', 'USERS')->where('entitas_id', $userId)->where('aksi', 'PASSWORD_RESET')->first();
    expect(json_decode($passwordAudit->data_baru, true))->toMatchArray(['oleh_admin_satker' => true]);
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
        'unit_id' => null,
        'mulai' => today()->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['unit_id']);

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

test('admin satker can manage its own satker metadata and child units but cannot access another satker', function (): void {
    [$admin, $root] = satkerAdminFixture();

    // Admin Satker hanya boleh mengelola struktur di bawah SATKER yang diberikan kepadanya.
    satkerAdminSession($admin)->postJson('/api/unit-organisasi', [
        'parent_id' => null,
        'kode' => 'SATKER-ROOT-NEW',
        'nama' => 'Root baru',
        'jenis' => 'SATKER',
    ])->assertConflict()->assertJsonPath('code', 'induk_di_luar_scope');
    $otherRoot = UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null]);
    satkerAdminSession($admin)->getJson('/api/unit-organisasi/'.$otherRoot->id)->assertNotFound();
    satkerAdminSession($admin)->patchJson('/api/unit-organisasi/'.$otherRoot->id, ['nama' => 'Satker asing'])
        ->assertNotFound();

    // Admin Satker boleh memperbarui identitas dasar SATKER sendiri saja.
    satkerAdminSession($admin)->patchJson('/api/unit-organisasi/'.$root->id, [
        'kode' => 'SATKER-OWN-UPDATED',
        'nama' => 'Satker Sendiri Diperbarui',
    ])->assertOk()->assertJsonPath('data.nama', 'Satker Sendiri Diperbarui');
    satkerAdminSession($admin)->patchJson('/api/unit-organisasi/'.$root->id, ['aktif' => false])
        ->assertConflict()->assertJsonPath('code', 'unit_satker_dilindungi');
    satkerAdminSession($admin)->patchJson('/api/unit-organisasi/'.$root->id, ['jenis' => 'BIDANG'])
        ->assertConflict()->assertJsonPath('code', 'unit_satker_dilindungi');
    satkerAdminSession($admin)->postJson('/api/unit-organisasi/'.$root->id.'/pindah-induk', ['parent_id' => null])
        ->assertConflict()->assertJsonPath('code', 'unit_satker_dilindungi');
    satkerAdminSession($admin)->deleteJson('/api/unit-organisasi/'.$root->id)
        ->assertConflict()->assertJsonPath('code', 'unit_satker_dilindungi');

    // Berhasil membuat unit anak (BIDANG) di bawah satkernya.
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

    // 5. Ditolak memindahkan unit anak ke luar wilayah atau ke null
    UnitOrganisasi::factory()->create(['jenis' => 'SATKER', 'parent_id' => null, 'kode' => 'RAHASIA-SATKER-B']);
    satkerAdminSession($admin)->postJson('/api/unit-organisasi', [
        'parent_id' => $root->id,
        'kode' => 'RAHASIA-SATKER-B',
        'nama' => 'Unit uji kode global',
        'jenis' => 'BIDANG',
    ])->assertUnprocessable()->assertJsonPath('errors.kode.0', 'Kode sudah digunakan.');
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
    satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $localPegawai->id,
        'unit_id' => $root->id,
        'mulai' => today()->toDateString(),
    ])->assertCreated();

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

    $sub = UnitOrganisasi::factory()->create(['jenis' => 'BIDANG', 'parent_id' => $root->id]);
    $newPlacement = satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $localPegawai->id,
        'unit_id' => $sub->id,
        'mulai' => today()->addDay()->toDateString(),
    ])->assertCreated()->json('data');
    expect($newPlacement['utama'])->toBeTrue();
    $this->assertDatabaseHas('PENEMPATAN_PEGAWAI', [
        'pegawai_id' => $localPegawai->id,
        'unit_id' => $root->id,
        'selesai' => today()->toDateString(),
    ]);
    satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $localPegawai->id,
        'unit_id' => $sub->id,
        'mulai' => today()->toDateString(),
        'selesai' => today()->addDays(2)->toDateString(),
    ])->assertConflict()->assertJsonPath('code', 'penempatan_tumpang_tindih');
    satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $localPegawai->id,
        'unit_id' => $sub->id,
        'mulai' => today()->addDays(3)->toDateString(),
        'utama' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors(['utama']);
});

test('placement edits reject closed-range overlap and preserve the original placement', function (): void {
    [$admin, $root] = satkerAdminFixture();
    $child = UnitOrganisasi::factory()->create(['jenis' => 'BIDANG', 'parent_id' => $root->id]);
    $pegawai = Pegawai::factory()->create();
    $first = satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $pegawai->id,
        'unit_id' => $root->id,
        'mulai' => today()->subDays(10)->toDateString(),
        'selesai' => today()->subDays(5)->toDateString(),
    ])->assertCreated()->json('data');
    satkerAdminSession($admin)->postJson('/api/penempatan-pegawai', [
        'pegawai_id' => $pegawai->id,
        'unit_id' => $child->id,
        'mulai' => today()->addDay()->toDateString(),
        'selesai' => today()->addDays(5)->toDateString(),
    ])->assertCreated();

    satkerAdminSession($admin)->patchJson('/api/penempatan-pegawai/'.$first['id'], [
        'unit_id' => $child->id,
        'selesai' => today()->addDays(2)->toDateString(),
    ])->assertConflict()->assertJsonPath('code', 'penempatan_tumpang_tindih');
    expect((int) $first['unit_id'])->toBe($root->id);
    $this->assertDatabaseHas('PENEMPATAN_PEGAWAI', [
        'id' => $first['id'],
        'unit_id' => $root->id,
        'selesai' => today()->subDays(5)->toDateString(),
    ]);
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
