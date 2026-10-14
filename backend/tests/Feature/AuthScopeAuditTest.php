<?php

use App\Models\LogAudit;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ScopeUnit;
use App\Services\TotpVerifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    Cache::flush();
});
test('unauthenticated me returns 401', function (): void {
    $this->getJson('/api/me')->assertUnauthorized();
});
test('login regenerates the session and records successful authentication without password data', function (): void {
    $user = User::factory()->create();
    $this->withSession(['marker' => 'old']);
    $old = session()->getId();
    $this->postJson('/login', ['login' => $user->email_login, 'password' => 'password-testing'])->assertOk()->assertJsonPath('data.user.id', $user->id)->assertJsonMissingPath('data.user.password_hash');
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($old);
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas_id' => $user->id, 'aksi' => 'LOGIN']);
    expect(DB::table('LOG_AUDIT')->get()->toJson())->not->toContain('password-testing')->not->toContain($user->password_hash);
});
test('invalid and inactive accounts are denied uniformly', function (bool $active): void {
    $user = User::factory()->create(['aktif' => $active]);
    $this->postJson('/login', ['login' => $user->username, 'password' => $active ? 'wrong' : 'password-testing'])->assertUnauthorized()->assertJsonPath('message', 'Kredensial tidak valid atau akun tidak dapat digunakan.');
    $this->assertGuest();
    $this->assertDatabaseHas('LOG_AUDIT', ['aksi' => 'LOGIN_FAILED']);
})->with([true, false]);
test('login failures cause temporary lockout', function (): void {
    $user = User::factory()->create();
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/login', ['login' => $user->username, 'password' => 'wrong'])->assertUnauthorized();
    }
    $this->postJson('/login', ['login' => $user->username, 'password' => 'password-testing'])->assertStatus(429)->assertJsonPath('code', 'login_terkunci');
    $this->travel(301)->seconds();
    $this->postJson('/login', ['login' => $user->username, 'password' => 'password-testing'])->assertOk();
});
test('forced password change remains possible and clears the flag', function (): void {
    $user = User::factory()->create(['wajib_ganti_password' => true]);
    $this->actingAs($user)->postJson('/api/ganti-password', ['current_password' => 'password-testing', 'password' => 'Changed-password-123', 'password_confirmation' => 'Changed-password-123'])->assertOk();
    expect($user->refresh()->wajib_ganti_password)->toBeFalse();
    expect(Hash::check('Changed-password-123', $user->password_hash))->toBeTrue();
    $this->assertDatabaseHas('LOG_AUDIT', ['aksi' => 'PASSWORD_CHANGE', 'entitas_id' => $user->id]);
});
test('wrong old password does not change credentials', function (): void {
    $user = User::factory()->create();
    $hash = $user->password_hash;
    $this->actingAs($user)->postJson('/api/ganti-password', ['current_password' => 'wrong', 'password' => 'Changed-password-123', 'password_confirmation' => 'Changed-password-123'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    expect($user->refresh()->password_hash)->toBe($hash);
});
test('logout removes authentication and invalidates session', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['marker' => 'old'])->postJson('/logout')->assertOk();
    $this->assertGuest('web');
    expect(session()->has('marker'))->toBeFalse();
});
test('inactive sessions cannot read me', function (): void {
    $this->actingAs(User::factory()->create(['aktif' => false]))->getJson('/api/me')->assertUnauthorized();
});
test('scope includes descendants but not another unit and ignores expired and future grants', function (): void {
    $user = User::factory()->create();
    $root = UnitOrganisasi::factory()->create();
    $child = UnitOrganisasi::factory()->create(['parent_id' => $root->id]);
    $other = UnitOrganisasi::factory()->create();
    grantRole($user, 'ADMIN_SATKER', $root->id);
    grantRole($user, 'STAFF', $other->id, ['mulai' => now()->addDay()]);
    grantRole($user, 'KABID', $other->id, ['selesai' => now()->subHour()]);
    expect(app(ScopeUnit::class)->ids($user))->toEqualCanonicalizing([$root->id, $child->id]);
});
test('a super admin with a null scope can see all units', function (): void {
    $user = User::factory()->create();
    UnitOrganisasi::factory()->count(2)->create();
    grantRole($user, 'SUPER_ADMIN');
    expect(app(ScopeUnit::class)->ids($user))->toHaveCount(2);
    expect(Gate::forUser($user)->allows('audit.view'))->toBeTrue();
});
test('ordinary roles cannot manage accounts or read audit', function (string $role): void {
    $user = User::factory()->create();
    grantRole($user, $role, UnitOrganisasi::factory()->create()->id);
    expect(Gate::forUser($user)->allows('admin.create', [User::class]))->toBeFalse();
    expect(Gate::forUser($user)->allows('audit.view'))->toBeFalse();
})->with(['ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']);
test('audit and model writes roll back together', function (): void {
    $count = DB::table('LOG_AUDIT')->count();
    try {
        DB::transaction(function (): void {
            User::factory()->create(['username' => 'rolled-back']);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException $e) {
    }
    $this->assertDatabaseMissing('USERS', ['username' => 'rolled-back']);
    $this->assertDatabaseCount('LOG_AUDIT', $count);
});
test('audit recursively removes sensitive fields and cannot be mutated through its model', function (): void {
    DB::transaction(fn () => app(AuditService::class)->record('TEST', 1, 'UPDATE', null, ['nama' => 'ok', 'nested' => ['password' => 'secret', 'api_token' => 'secret', 'isi_file' => 'private', 'nama' => 'visible']]));
    $log = LogAudit::where('entitas', 'TEST')->firstOrFail();
    expect($log->data_baru)->toBe(['nama' => 'ok', 'nested' => ['nama' => 'visible']]);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(fn () => $log->forceFill(['aksi' => 'OTHER'])->save())->toThrow(LogicException::class);
});
test('csrf protection rejects login without a valid token outside testing bypass', function (): void {
    $this->app->instance('env', 'local');
    $this->postJson('/login', ['login' => 'someone', 'password' => 'secret'])->assertStatus(419);
});
test('cors allows only configured frontend origins', function (): void {
    $this->withHeader('Origin', 'http://localhost:5173')->get('/sanctum/csrf-cookie')->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')->assertHeader('Access-Control-Allow-Credentials', 'true');
    $this->withHeader('Origin', 'https://untrusted.example')->get('/sanctum/csrf-cookie')->assertHeaderMissing('Access-Control-Allow-Origin');
});
test('TOTP verifier accepts a known RFC vector once and rejects replay', function (): void {
    $this->travelTo(Carbon::createFromTimestamp(59));
    $user = User::factory()->create();
    config(['security.totp_secrets' => [$user->id => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ']]);
    expect(app(TotpVerifier::class)->verify($user, '287082'))->toBeTrue();
    expect(app(TotpVerifier::class)->verify($user, '287082'))->toBeFalse();
});
test('enabled super admin TOTP fails closed without provisioning', function (): void {
    $user = User::factory()->create();
    grantRole($user, 'SUPER_ADMIN');
    config(['security.super_admin_totp' => true]);
    $this->postJson('/login', ['login' => $user->username, 'password' => 'password-testing'])->assertUnauthorized();
});
