<?php

use App\Exceptions\BusinessRuleException;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\PeriodePenilaian;
use App\Models\RencanaKerja;
use App\Models\Role;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\AdminService;
use App\Services\AuditService;
use App\Services\ScopeUnit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    $this->withHeader('Origin', 'http://localhost:5173');
});
function adminActor(): User
{
    $user = User::factory()->create();
    grantRole($user, 'SUPER_ADMIN');

    return $user;
}
function adminSession(User $user): mixed
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
}
test('super admin can create, edit, move, and deactivate organization units at each valid level', function (): void {
    $super = adminActor();
    $root = adminSession($super)->postJson('/api/unit-organisasi', [
        'parent_id' => null,
        'kode' => 'SATKER-CRUD',
        'nama' => 'Satker CRUD',
        'jenis' => 'SATKER',
    ])->assertCreated()->json('data');
    $bidang = $this->postJson('/api/unit-organisasi', [
        'parent_id' => $root['id'],
        'kode' => 'BID-CRUD',
        'nama' => 'Bidang CRUD',
        'jenis' => 'BIDANG',
    ])->assertCreated()->json('data');
    $targetBidang = $this->postJson('/api/unit-organisasi', [
        'parent_id' => $root['id'],
        'kode' => 'BID-TARGET',
        'nama' => 'Bidang Target',
        'jenis' => 'BIDANG',
    ])->assertCreated()->json('data');
    $this->patchJson('/api/unit-organisasi/'.$root['id'], ['nama' => 'Satker Diperbarui'])
        ->assertOk()->assertJsonPath('data.nama', 'Satker Diperbarui');
    $subbidang = $this->postJson('/api/unit-organisasi', [
        'parent_id' => $bidang['id'],
        'kode' => 'SUBBID-CRUD',
        'nama' => 'Subbidang CRUD',
        'jenis' => 'SUBBIDANG',
    ])->assertCreated()->json('data');
    $this->postJson('/api/unit-organisasi/'.$subbidang['id'].'/pindah-induk', ['parent_id' => $targetBidang['id']])
        ->assertOk()->assertJsonPath('data.parent_id', $targetBidang['id']);
    $leaf = $this->postJson('/api/unit-organisasi', [
        'parent_id' => $root['id'],
        'kode' => 'BID-LEAF',
        'nama' => 'Bidang Mandiri',
        'jenis' => 'BIDANG',
    ])->assertCreated()->json('data');
    $this->deleteJson('/api/unit-organisasi/'.$leaf['id'])->assertOk();
    $emptyRoot = $this->postJson('/api/unit-organisasi', [
        'parent_id' => null,
        'kode' => 'SATKER-EMPTY',
        'nama' => 'Satker Kosong',
        'jenis' => 'SATKER',
    ])->assertCreated()->json('data');
    $this->deleteJson('/api/unit-organisasi/'.$emptyRoot['id'])->assertOk();
    expect((bool) UnitOrganisasi::whereKey($emptyRoot['id'])->value('aktif'))->toBeFalse();
});
test('administration requires authentication', function (string $resource): void {
    $this->getJson('/api/'.$resource)->assertUnauthorized();
})->with(array_keys(AdminService::MODELS));
test('the read permission matrix is enforced over HTTP', function (string $role, string $resource): void {
    $user = User::factory()->create();
    grantRole($user, $role, $role === 'SUPER_ADMIN' ? null : UnitOrganisasi::factory()->create()->id);
    $response = $this->actingAs($user)->getJson('/api/'.$resource);
    if ($role === 'ADMIN_SATKER' && in_array($resource, ['periode-penilaian', 'rencana-kerja'], true)) {
        $response->assertForbidden();
    } elseif ($role !== 'SUPER_ADMIN' && $role !== 'ADMIN_SATKER' && in_array($resource, ['users', 'user-role-scope', 'roles'], true)) {
        $response->assertForbidden();
    } else {
        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF'])->with(array_keys(AdminService::MODELS));
test('non super roles cannot write administrative resources over HTTP', function (string $role, string $resource): void {
    $user = User::factory()->create();
    grantRole($user, $role, UnitOrganisasi::factory()->create()->id);
    adminSession($user)->postJson('/api/'.$resource, [])->assertForbidden();
})->with(['KASATKER', 'KABID', 'KASUB', 'STAFF'])->with(['users', 'user-role-scope', 'unit-organisasi', 'pegawai', 'penempatan-pegawai', 'periode-penilaian', 'rencana-kerja']);
test('super admin can create users with hashed credentials and required change', function (): void {
    $admin = adminActor();
    $this->actingAs($admin)->postJson('/api/users', ['username' => 'new-admin-user', 'email_login' => 'new@example.test', 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'])->assertCreated()->assertJsonPath('data.wajib_ganti_password', true)->assertJsonMissingPath('data.password_hash');
    $user = User::where('username', 'new-admin-user')->firstOrFail();
    expect(Hash::check('New-password-123', $user->password_hash))->toBeTrue();
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'USERS', 'entitas_id' => $user->id, 'aksi' => 'CREATE', 'user_id' => $admin->id]);
});
test('forced password change blocks administrative access', function (): void {
    $admin = adminActor();
    $admin->update(['wajib_ganti_password' => true]);
    $this->actingAs($admin)->getJson('/api/users')->assertForbidden()->assertJsonPath('code', 'wajib_ganti_password');
});
test('password reset needs recent confirmation and hides the replacement password', function (): void {
    $admin = adminActor();
    $target = User::factory()->create();
    $payload = ['password' => 'Reset-password-123', 'password_confirmation' => 'Reset-password-123'];
    $this->actingAs($admin)->postJson('/api/users/'.$target->id.'/reset-password', $payload)->assertForbidden()->assertJsonPath('code', 'konfirmasi_password_diperlukan');
    $this->withSession(['auth.password_confirmed_at' => now()->subMinutes(6)->timestamp])->postJson('/api/users/'.$target->id.'/reset-password', $payload)->assertForbidden();
    adminSession($admin)->postJson('/api/users/'.$target->id.'/reset-password', $payload)->assertOk()->assertJsonMissingPath('data.password_hash');
    expect(Hash::check('Reset-password-123', $target->refresh()->password_hash))->toBeTrue();
    expect($target->wajib_ganti_password)->toBeTrue();
    $passwordAudit = DB::table('LOG_AUDIT')->where('entitas_id', $target->id)->where('aksi', 'PASSWORD_RESET')->first();
    expect(json_decode($passwordAudit->data_baru, true))->toMatchArray(['oleh_admin_satker' => false]);
});
test('super admin cannot deactivate or delete its own account', function (string $method, string $suffix, string $code): void {
    $admin = adminActor();
    $this->actingAs($admin)->json($method, '/api/users/'.$admin->id.$suffix, ['aktif' => false])->assertConflict()->assertJsonPath('code', $code);
    expect($admin->refresh()->aktif)->toBeTrue();
})->with([['POST', '/nonaktifkan', 'tidak_boleh_nonaktifkan_diri'], ['DELETE', '', 'tidak_boleh_hapus_diri'], ['PATCH', '', 'tidak_boleh_nonaktifkan_diri']]);
test('deleting a user permanently removes the account and its role scopes', function (): void {
    $admin = adminActor();
    $target = User::factory()->create();
    $scope = UserRoleScope::factory()->create(['user_id' => $target->id]);
    $this->actingAs($admin)->deleteJson('/api/users/'.$target->id)->assertOk();
    $this->assertDatabaseMissing('USERS', ['id' => $target->id]);
    $this->assertDatabaseMissing('USER_ROLE_SCOPE', ['id' => $scope->id]);
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'USERS', 'entitas_id' => $target->id, 'aksi' => 'DELETE']);
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'USER_ROLE_SCOPE', 'entitas_id' => $scope->id, 'aksi' => 'ROLE_CHANGE']);
});
test('deleting a user is blocked when audit history references the account', function (): void {
    $admin = adminActor();
    $target = User::factory()->create();
    DB::table('LOG_AUDIT')->insert([
        'user_id' => $target->id,
        'entitas' => 'USERS',
        'entitas_id' => $target->id,
        'aksi' => 'UPDATE',
        'data_lama' => null,
        'data_baru' => null,
        'ip_address' => '127.0.0.1',
        'waktu' => now(),
    ]);

    $this->actingAs($admin)->deleteJson('/api/users/'.$target->id)
        ->assertConflict()->assertJsonPath('code', 'pengguna_masih_digunakan');
    $this->assertDatabaseHas('USERS', ['id' => $target->id]);
});
test('deleting an employee is blocked when placement history references the employee', function (): void {
    $admin = adminActor();
    $employee = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $employee->id]);

    $this->actingAs($admin)->deleteJson('/api/pegawai/'.$employee->id)
        ->assertConflict()->assertJsonPath('code', 'pegawai_masih_digunakan');
    $this->assertDatabaseHas('PEGAWAI', ['id' => $employee->id]);
});
test('scope unit nullability follows R1', function (string $roleCode, bool $withUnit): void {
    $admin = adminActor();
    $target = User::factory()->create();
    $role = Role::firstOrCreate(['kode' => $roleCode], ['nama' => $roleCode]);
    adminSession($admin)->postJson('/api/user-role-scope', ['user_id' => $target->id, 'role_id' => $role->id, 'unit_id' => $withUnit ? UnitOrganisasi::factory()->create()->id : null, 'mulai' => now()->toIso8601String()])->assertConflict()->assertJsonPath('code', 'scope_unit_tidak_valid');
})->with([['SUPER_ADMIN', true], ['STAFF', false]]);
test('scope creation accepts a valid grant and rejects overlapping ranges', function (): void {
    $admin = adminActor();
    $target = User::factory()->create();
    $unit = UnitOrganisasi::factory()->create();
    $role = Role::factory()->create();
    $payload = ['user_id' => $target->id, 'role_id' => $role->id, 'unit_id' => $unit->id, 'mulai' => '2026-01-01T00:00:00+07:00', 'selesai' => '2026-12-31T23:59:59+07:00'];
    $firstScopeId = adminSession($admin)->postJson('/api/user-role-scope', $payload)->assertCreated()->assertJsonPath('data.mulai', '2026-01-01T00:00:00+07:00')->json('data.id');
    $this->postJson('/api/user-role-scope', $payload)->assertConflict()->assertJsonPath('code', 'scope_tumpang_tindih');
    $secondScopeId = $this->postJson('/api/user-role-scope', [...$payload, 'mulai' => '2027-01-01', 'selesai' => null])->assertCreated()->json('data.id');
    $scopeAudit = DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $firstScopeId)->where('aksi', 'ROLE_CHANGE')->first();
    expect(json_decode($scopeAudit->data_baru, true))->toMatchArray([
        'oleh_admin_satker' => false,
        'peran' => $role->kode,
    ]);
    expect(DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $firstScopeId)->where('aksi', 'ROLE_CHANGE')->count())->toBe(1);
    expect(DB::table('LOG_AUDIT')->where('entitas', 'USER_ROLE_SCOPE')->where('entitas_id', $secondScopeId)->where('aksi', 'ROLE_CHANGE')->count())->toBe(1);
});
test('invalid scope dates and self revocation are refused', function (): void {
    $admin = adminActor();
    $scope = UserRoleScope::where('user_id', $admin->id)->firstOrFail();
    adminSession($admin)->deleteJson('/api/user-role-scope/'.$scope->id)->assertConflict()->assertJsonPath('code', 'tidak_boleh_cabut_role_sendiri');
    $this->patchJson('/api/user-role-scope/'.$scope->id, ['selesai' => '2000-01-01'])->assertConflict()->assertJsonPath('code', 'rentang_scope_tidak_valid');
    $this->assertDatabaseHas('USER_ROLE_SCOPE', ['id' => $scope->id]);
});
test('unit hierarchy rejects cycles and invalid levels', function (): void {
    $admin = adminActor();
    $root = UnitOrganisasi::factory()->create();
    $child = UnitOrganisasi::factory()->create(['parent_id' => $root->id, 'jenis' => 'BIDANG']);
    $this->actingAs($admin)->postJson('/api/unit-organisasi/'.$root->id.'/pindah-induk', ['parent_id' => $child->id])->assertConflict()->assertJsonPath('code', 'siklus_hierarki');
    $this->postJson('/api/unit-organisasi', ['parent_id' => $root->id, 'kode' => 'BAD', 'nama' => 'Bad', 'jenis' => 'SUBBAGIAN'])->assertConflict()->assertJsonPath('code', 'level_unit_tidak_valid');
    $this->assertDatabaseMissing('UNIT_ORGANISASI', ['kode' => 'BAD']);
});
test('unit creation tree and valid parent movement work', function (): void {
    $admin = adminActor();
    $root = UnitOrganisasi::factory()->create();
    $other = UnitOrganisasi::factory()->create();
    $response = $this->actingAs($admin)->postJson('/api/unit-organisasi', ['parent_id' => $root->id, 'kode' => 'BID-NEW', 'nama' => 'Bidang baru', 'jenis' => 'BIDANG'])->assertCreated();
    $id = $response->json('data.id');
    $this->postJson('/api/unit-organisasi/'.$id.'/pindah-induk', ['parent_id' => $other->id])->assertOk()->assertJsonPath('data.parent_id', $other->id);
    $this->getJson('/api/unit-organisasi/pohon')->assertOk()->assertJsonCount(2, 'data');
});
test('a unit with children or active employees cannot be deactivated', function (bool $hasChild): void {
    $admin = adminActor();
    $unit = UnitOrganisasi::factory()->create();
    if ($hasChild) {
        UnitOrganisasi::factory()->create(['parent_id' => $unit->id, 'jenis' => 'BIDANG', 'aktif' => false]);
    } else {
        PenempatanPegawai::factory()->create(['unit_id' => $unit->id]);
    }
    $this->actingAs($admin)->deleteJson('/api/unit-organisasi/'.$unit->id)->assertConflict()->assertJsonPath('code', 'unit_masih_digunakan');
    expect($unit->refresh()->aktif)->toBeTrue();
})->with([true, false]);
test('new primary placement closes the old placement atomically', function (): void {
    $admin = adminActor();
    $pegawai = Pegawai::factory()->create();
    $old = PenempatanPegawai::factory()->create(['pegawai_id' => $pegawai->id, 'mulai' => '2026-01-01']);
    $unit = UnitOrganisasi::factory()->create();
    $this->actingAs($admin)->postJson('/api/penempatan-pegawai', ['pegawai_id' => $pegawai->id, 'unit_id' => $unit->id, 'mulai' => '2026-02-01'])->assertCreated();
    expect($old->refresh()->selesai->toDateString())->toBe('2026-01-31');
    expect(PenempatanPegawai::where('pegawai_id', $pegawai->id)->whereNull('selesai')->count())->toBe(1);
    $this->getJson('/api/pegawai/'.$pegawai->id.'/jalur')->assertOk()->assertJsonPath('data.satker_id', $unit->id);
});
test('invalid placement start cannot close previous placement or write audit', function (): void {
    $admin = adminActor();
    $old = PenempatanPegawai::factory()->create(['mulai' => '2026-02-01']);
    $logs = DB::table('LOG_AUDIT')->count();
    $this->actingAs($admin)->postJson('/api/penempatan-pegawai', ['pegawai_id' => $old->pegawai_id, 'unit_id' => $old->unit_id, 'mulai' => '2026-01-01'])->assertConflict();
    expect($old->refresh()->selesai)->toBeNull();
    $this->assertDatabaseCount('LOG_AUDIT', $logs);
});
test('scoped list and direct ids never expose another unit or its employees', function (string $role): void {
    $unit = UnitOrganisasi::factory()->create();
    $other = UnitOrganisasi::factory()->create();
    $mine = PenempatanPegawai::factory()->create(['unit_id' => $unit->id]);
    $outside = PenempatanPegawai::factory()->create(['unit_id' => $other->id]);
    $user = User::factory()->create(['pegawai_id' => $mine->pegawai_id]);
    grantRole($user, $role, $unit->id);
    $this->actingAs($user)->getJson('/api/pegawai')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->pegawai_id);
    foreach (['unit-organisasi' => $other->id, 'pegawai' => $outside->pegawai_id, 'penempatan-pegawai' => $outside->id] as $resource => $id) {
        $this->getJson('/api/'.$resource.'/'.$id)->assertNotFound();
        $this->patchJson('/api/'.$resource.'/'.$id, ['nama' => 'leak'])->assertNotFound();
    }
    $this->getJson('/api/pegawai/'.$outside->pegawai_id.'/jalur')->assertNotFound();
})->with(['ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']);
test('staff can only read its own employee record inside its unit', function (): void {
    $mine = PenempatanPegawai::factory()->create();
    $peer = PenempatanPegawai::factory()->create(['unit_id' => $mine->unit_id]);
    $user = User::factory()->create(['pegawai_id' => $mine->pegawai_id]);
    grantRole($user, 'STAFF', $mine->unit_id);
    $this->actingAs($user)->getJson('/api/pegawai/'.$peer->pegawai_id)->assertNotFound();
});
test('pagination sorting filters and injection rejection are enforced', function (): void {
    $admin = adminActor();
    Pegawai::factory()->count(3)->create();
    $this->actingAs($admin)->getJson('/api/pegawai?per_page=2&sort=-nama&filter[aktif]=1')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
    $this->getJson('/api/pegawai?per_page=101')->assertUnprocessable();
    $this->getJson('/api/pegawai?sort=nama%3Bdrop%20table')->assertUnprocessable();
    $this->getJson('/api/users?filter[password_hash]=test')->assertUnprocessable();
});
test('employee period and work plan CRUD are audited', function (): void {
    $admin = adminActor();
    $response = $this->actingAs($admin)->postJson('/api/pegawai', ['nip' => null, 'nama' => 'Pegawai Baru', 'email_kontak' => 'pegawai@example.test', 'jenis_kepegawaian' => 'NON_PNS'])->assertCreated();
    $id = $response->json('data.id');
    $this->patchJson('/api/pegawai/'.$id, ['nama' => 'Pegawai Diubah'])->assertOk();
    $this->deleteJson('/api/pegawai/'.$id)->assertOk();
    $this->assertDatabaseMissing('PEGAWAI', ['id' => $id]);
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'PEGAWAI', 'entitas_id' => $id, 'aksi' => 'DELETE']);
    foreach (['periode-penilaian' => ['nama' => '2026', 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31'], 'rencana-kerja' => ['kode' => 'RK-1', 'uraian_template' => 'Uraian']] as $resource => $payload) {
        $response = $this->postJson('/api/'.$resource, $payload)->assertCreated();
        $record = $response->json('data.id');
        $this->getJson('/api/'.$resource.'/'.$record)->assertOk();
        $this->patchJson('/api/'.$resource.'/'.$record, $payload)->assertOk();
        $this->deleteJson('/api/'.$resource.'/'.$record)->assertOk();
    }
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'PEGAWAI', 'entitas_id' => $id, 'aksi' => 'UPDATE']);
});
test('date bounds and unexpected privileged fields are validated', function (): void {
    $admin = adminActor();
    $this->actingAs($admin)->postJson('/api/periode-penilaian', ['nama' => 'Bad', 'tanggal_mulai' => '2026-12-31', 'tanggal_selesai' => '2026-01-01'])->assertConflict();
    $this->patchJson('/api/users/'.$admin->id, ['password_hash' => 'raw'])->assertUnprocessable()->assertJsonValidationErrors('password_hash');
    $this->postJson('/api/pegawai', ['nip' => str_repeat('1', 19), 'nama' => 'Nama', 'email_kontak' => 'valid@example.test', 'jenis_kepegawaian' => 'PNS'])->assertUnprocessable()->assertJsonValidationErrors('nip');
});

test('NIP accepts one to eighteen digits and is masked until password confirmation', function (): void {
    $admin = adminActor();
    $employee = Pegawai::factory()->create(['nip' => '012345678901234567']);
    $admin->update(['pegawai_id' => $employee->id]);
    $this->actingAs($admin)->getJson('/api/me')
        ->assertOk()->assertJsonPath('data.pegawai.nip_tersedia', true)
        ->assertJsonPath('data.pegawai.nip', str_repeat('•', 18));
    $this->putJson('/api/profil', [
        'nama' => $employee->nama,
        'email' => $employee->email_kontak,
        'nip' => 'nip-tidak-valid',
    ])->assertUnprocessable()->assertJsonValidationErrors('nip');
    $response = $this->actingAs($admin)->getJson('/api/pegawai?filter[nama]='.$employee->nama)
        ->assertOk()
        ->assertJsonPath('data.0.nip_tersedia', true)
        ->assertJsonPath('data.0.nip', str_repeat('•', 18));
    $employeeId = $response->json('data.0.id');

    $this->getJson('/api/pegawai?filter[nip]=012345678901234567')->assertUnprocessable();
    $this->postJson('/api/pegawai/'.$employeeId.'/lihat-nip', ['password' => 'wrong-password'])->assertUnprocessable();
    $this->postJson('/api/pegawai/'.$employeeId.'/lihat-nip', ['password' => 'password-testing'])
        ->assertOk()->assertJsonPath('data.nip', '012345678901234567');

    $short = $this->postJson('/api/pegawai', [
        'nip' => '7',
        'nama' => 'Pegawai NIP pendek',
        'email_kontak' => 'short-nip@example.test',
        'jenis_kepegawaian' => 'PNS',
    ])->assertCreated();
    expect($short->json('data.nip_tersedia'))->toBeTrue();
});

test('non super roles cannot update or delete in-scope administrative records', function (string $role, string $resource, string $method): void {
    $placement = PenempatanPegawai::factory()->create();
    $user = User::factory()->create(['pegawai_id' => $placement->pegawai_id]);
    $grant = grantRole($user, $role, $placement->unit_id);
    $id = match ($resource) {
        'users' => $user->id,
        'user-role-scope' => $grant->id,
        'unit-organisasi' => $placement->unit_id,
        'pegawai' => $placement->pegawai_id,
        'penempatan-pegawai' => $placement->id,
        'periode-penilaian' => PeriodePenilaian::factory()->create()->id,
        'rencana-kerja' => RencanaKerja::factory()->create()->id,
    };
    adminSession($user)->json($method, '/api/'.$resource.'/'.$id, [])->assertForbidden();
})->with(['KASATKER', 'KABID', 'KASUB', 'STAFF'])->with(['users', 'user-role-scope', 'unit-organisasi', 'pegawai', 'penempatan-pegawai', 'periode-penilaian', 'rencana-kerja'])->with(['PATCH', 'DELETE']);

test('recent password confirmation is established by verifying the actual password', function (): void {
    $admin = adminActor();
    $this->actingAs($admin)->postJson('/api/konfirmasi-password', ['password' => 'wrong'])->assertUnprocessable();
    $this->postJson('/api/konfirmasi-password', ['password' => 'password-testing'])->assertOk()->assertSessionHas('auth.password_confirmed_at');
});

test('removing another super grant leaves an active super admin and invalidates removed access', function (): void {
    $admin = adminActor();
    $other = adminActor();
    $scope = UserRoleScope::where('user_id', $other->id)->firstOrFail();
    adminSession($admin)->deleteJson('/api/user-role-scope/'.$scope->id)->assertOk();
    expect(app(ScopeUnit::class)->isSuper($admin))->toBeTrue();
    expect(app(ScopeUnit::class)->isSuper($other))->toBeFalse();
    $this->assertDatabaseMissing('USER_ROLE_SCOPE', ['id' => $scope->id]);
});

test('editing role dates preserves nonoverlap and can revoke another users scope', function (): void {
    $admin = adminActor();
    $target = User::factory()->create();
    $unit = UnitOrganisasi::factory()->create();
    $scope = grantRole($target, 'STAFF', $unit->id, ['mulai' => '2020-01-01']);
    adminSession($admin)->patchJson('/api/user-role-scope/'.$scope->id, ['selesai' => '2021-01-01'])->assertOk();
    expect(app(ScopeUnit::class)->ids($target))->toBe([]);
});

test('an existing placement can be closed and audited', function (): void {
    $admin = adminActor();
    $placement = PenempatanPegawai::factory()->create(['mulai' => '2026-01-01']);
    $this->actingAs($admin)->patchJson('/api/penempatan-pegawai/'.$placement->id, ['selesai' => '2026-02-01'])->assertOk()->assertJsonPath('data.selesai', '2026-02-01');
    expect($placement->refresh()->kunci_utama_aktif)->toBeNull();
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'PENEMPATAN_PEGAWAI', 'entitas_id' => $placement->id, 'aksi' => 'UPDATE']);
});

test('audited writes fail atomically if audit persistence fails', function (): void {
    $admin = adminActor();
    $pegawai = Pegawai::factory()->create(['nama' => 'Original']);
    $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new RuntimeException('Audit unavailable'));
    $this->actingAs($admin)->patchJson('/api/pegawai/'.$pegawai->id, ['nama' => 'Changed'])->assertStatus(500);
    expect($pegawai->refresh()->nama)->toBe('Original');
});

test('organization units cannot be physically deleted through models', function (): void {
    $class = UnitOrganisasi::class;
    $model = $class::factory()->create();
    expect(fn () => $model->delete())->toThrow(BusinessRuleException::class);
    $this->assertModelExists($model);
});

test('OpenAPI includes implemented routes and is restricted outside local', function (): void {
    $this->getJson('/docs/api.json')->assertForbidden();
    $admin = adminActor();
    $response = $this->actingAs($admin)->getJson('/docs/api.json')->assertOk();
    $paths = $response->json('paths');
    expect($paths)->toHaveKeys(['/login', '/logout', '/api/me', '/api/users', '/api/user-role-scope', '/api/unit-organisasi', '/api/pegawai', '/api/penempatan-pegawai', '/api/periode-penilaian', '/api/rencana-kerja']);
});
