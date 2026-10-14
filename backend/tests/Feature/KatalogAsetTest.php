<?php

use App\Models\Aset;
use App\Models\JenisAncaman;
use App\Models\KategoriAset;
use App\Models\Kontrol;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\PenugasanAset;
use App\Models\SinkronAset;
use App\Models\SumberAset;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Services\AuditService;
use App\Services\KatalogService;
use App\Services\SinkronAsetService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    Http::preventStrayRequests();
});
function katalogActor(string $role = 'SUPER_ADMIN', ?UnitOrganisasi $unit = null): User
{
    $user = User::factory()->create();
    grantRole($user, $role, $role === 'SUPER_ADMIN' ? null : ($unit ?? UnitOrganisasi::factory()->create())->id);

    return $user;
}
function asetSyncSetup(): User
{
    $user = katalogActor();
    $unit = UnitOrganisasi::factory()->create(['kode' => 'DEFAULT', 'jenis' => 'SATKER']);
    SumberAset::factory()->create(['kode' => 'API_ASET']);
    KategoriAset::factory()->create(['kode' => 'BELUM_DIKATEGORIKAN']);
    config(['aset.url' => 'http://aset.test/api/aset', 'aset.token' => 'secret-test-token', 'aset.unit_bawaan_kode' => $unit->kode, 'queue.default' => 'sync']);

    return $user;
}
function asetFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/aset_api_contoh.json')), true);
}
test('catalog resources require authentication', function (string $resource): void {
    $this->getJson('/api/'.$resource)->assertUnauthorized();
    $this->postJson('/api/'.$resource)->assertUnauthorized();
})->with(array_keys(KatalogService::MODELS));
test('catalog read and mutation permission matrix is enforced', function (string $role, string $resource): void {
    $user = katalogActor($role);
    $class = KatalogService::MODELS[$resource];
    $model = $class::factory()->create();
    $url = '/api/'.$resource.'/'.$model->id;
    $this->actingAs($user)->getJson('/api/'.$resource)->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    $this->getJson($url)->assertOk();
    if ($role !== 'SUPER_ADMIN') {
        $this->postJson('/api/'.$resource, [])->assertForbidden();
        $this->patchJson($url, [])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
    } else {
        $this->patchJson($url, ['nama' => 'Nama baru'])->assertOk()->assertJsonPath('data.nama', 'Nama baru');
        $this->deleteJson($url)->assertOk();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF'])->with(array_keys(KatalogService::MODELS));
test('super admin creates every catalog via validated API', function (string $resource): void {
    $user = katalogActor();
    $class = KatalogService::MODELS[$resource];
    $model = $class::factory()->make();
    $payload = $model->only($model->getFillable());
    $this->actingAs($user)->postJson('/api/'.$resource, $payload)->assertCreated();
    $this->assertDatabaseHas($model->getTable(), ['nama' => $model->nama]);
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => $model->getTable(), 'aksi' => 'CREATE', 'user_id' => $user->id]);
})->with(array_keys(KatalogService::MODELS));
test('category and control reject cycles', function (string $resource): void {
    $user = katalogActor();
    $class = KatalogService::MODELS[$resource];
    $parent = $class::factory()->create();
    $data = ['parent_id' => $parent->id];
    if ($parent instanceof Kontrol) {
        $data['standar_id'] = $parent->standar_id;
    } $child = $class::factory()->create($data);
    $this->actingAs($user)->patchJson('/api/'.$resource.'/'.$parent->id, ['parent_id' => $child->id])->assertConflict()->assertJsonPath('code', 'siklus_hierarki');
    expect($parent->fresh()->parent_id)->toBeNull();
})->with(['kategori-aset', 'kontrol']);
test('CSV import reports invalid rows and export neutralizes formulas', function (): void {
    $user = katalogActor();
    $type = JenisAncaman::factory()->create();
    $csv = "jenis_ancaman_id,kode,nama,deskripsi\n{$type->id},A1,=SUM(1),Deskripsi\n999999,A2,Salah,Deskripsi\n{$type->id},A3,Benar,Deskripsi\n";
    $this->actingAs($user)->postJson('/api/ancaman/impor', ['file' => UploadedFile::fake()->createWithContent('ancaman.csv', $csv)])->assertOk()->assertJsonPath('data.berhasil', 2)->assertJsonPath('data.gagal', 1);
    $response = $this->get('/api/ancaman/ekspor')->assertOk();
    expect($response->streamedContent())->toContain("'=SUM(1)");
    $this->assertDatabaseCount('ANCAMAN', 2);
});
test('catalog list validates filter and sorting', function (): void {
    $this->actingAs(katalogActor())->getJson('/api/ancaman?sort=id%20desc')->assertUnprocessable();
    $this->getJson('/api/ancaman?filter[secret]=x')->assertUnprocessable();
    $this->getJson('/api/ancaman?per_page=101')->assertUnprocessable();
});
test('asset roles can read in scope but only admin satker can edit local fields', function (string $role): void {
    $unit = UnitOrganisasi::factory()->create();
    $user = katalogActor($role, $unit);
    $asset = Aset::factory()->create(['unit_pemilik_id' => $unit->id]);
    $this->actingAs($user)->getJson('/api/aset/'.$asset->id)->assertOk()->assertJsonMissingPath('data.data_mentah');
    $response = $this->patchJson('/api/aset/'.$asset->id, ['lokasi' => 'Lab']);
    if ($role === 'ADMIN_SATKER') {
        $response->assertOk();
        expect($asset->fresh()->lokasi)->toBe('Lab');
    } else {
        $response->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']);
test('asset scope applies before validation and blocks unrelated role escalation', function (): void {
    $unit = UnitOrganisasi::factory()->create();
    $other = UnitOrganisasi::factory()->create();
    $user = katalogActor('ADMIN_SATKER', $unit);
    $foreign = Aset::factory()->create(['unit_pemilik_id' => $other->id]);
    $this->actingAs($user)->getJson('/api/aset/'.$foreign->id)->assertNotFound();
    $this->patchJson('/api/aset/'.$foreign->id, ['nama' => 'X'])->assertNotFound();
    grantRole($user, 'STAFF', $other->id);
    $this->patchJson('/api/aset/'.$foreign->id, ['lokasi' => 'Lab'])->assertForbidden();
});
test('bulk assignment reports out of scope rows without exposing them', function (): void {
    $unit = UnitOrganisasi::factory()->create();
    $user = katalogActor('ADMIN_SATKER', $unit);
    $asset = Aset::factory()->create(['unit_pemilik_id' => $unit->id]);
    $foreign = Aset::factory()->create();
    $category = KategoriAset::factory()->create();
    $this->actingAs($user)->postJson('/api/aset/tetapkan', ['ids' => [$asset->id, $foreign->id], 'unit_pemilik_id' => $unit->id, 'kategori_id' => $category->id])->assertOk()->assertJsonPath('data.berhasil', 1)->assertJsonPath('data.gagal', 1);
    expect($asset->fresh()->perlu_ditinjau)->toBeFalse();
    expect($foreign->fresh()->perlu_ditinjau)->toBeTrue();
    $this->patchJson('/api/aset/'.$asset->id, ['nama' => 'Sumber diganti'])->assertUnprocessable()->assertJsonValidationErrors('nama');
});
test('assignment CRUD validates employee and asset scope', function (): void {
    $unit = UnitOrganisasi::factory()->create();
    $user = katalogActor('ADMIN_SATKER', $unit);
    $asset = Aset::factory()->create(['unit_pemilik_id' => $unit->id]);
    $employee = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $employee->id, 'unit_id' => $unit->id, 'mulai' => today()->subDay()]);
    $id = $this->actingAs($user)->postJson('/api/penugasan-aset', ['aset_id' => $asset->id, 'pegawai_id' => $employee->id, 'jenis_tanggung_jawab' => 'PIC', 'mulai' => '2026-01-01'])->assertCreated()->json('data.id');
    $this->patchJson('/api/penugasan-aset/'.$id, ['selesai' => '2025-01-01'])->assertConflict();
    $this->patchJson('/api/penugasan-aset/'.$id, ['selesai' => '2026-12-31'])->assertOk();
    $this->deleteJson('/api/penugasan-aset/'.$id)->assertOk();
    $this->assertDatabaseMissing('PENUGASAN_ASET', ['id' => $id]);
});
test('synthetic 197 row fixture is normalized and repeated sync is idempotent', function (): void {
    $user = asetSyncSetup();
    Http::fake(['aset.test/*' => Http::response(asetFixture())]);
    $this->actingAs($user)->postJson('/api/aset-sinkron/jalankan')->assertAccepted()->assertJsonPath('data.status', 'SUKSES')->assertJsonPath('data.jumlah_dibuat', 197);
    $this->postJson('/api/aset-sinkron/jalankan')->assertAccepted()->assertJsonPath('data.jumlah_dibuat', 0)->assertJsonPath('data.jumlah_diubah', 0)->assertJsonPath('data.jumlah_tidak_berubah', 197);
    expect(Aset::count())->toBe(197);
    expect(Aset::where('external_id', '250')->firstOrFail()->kode_aset)->toBe('API_ASET-250');
    $last = Aset::where('external_id', '454')->firstOrFail();
    expect($last->jumlah)->toBe(12);
    expect($last->kondisi)->toBe('BAIK');
    expect($last->lokasi)->toBe('');
    expect($last->tahun_pengadaan)->toBe(2022);
    expect(Aset::where('nomor_inventaris', 'PRT/2025/IPDC/001')->count())->toBe(2);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-test-token'));
    expect(DB::table('LOG_AUDIT')->get()->toJson())->not->toContain('secret-test-token');
    expect(SinkronAset::latest('id')->first()->peringatan)->not->toBeEmpty();
});
test('source changes preserve local columns and asset code while missing rows reactivate', function (): void {
    $user = asetSyncSetup();
    $rows = array_slice(asetFixture()['data'], 0, 2);
    $changed = $rows;
    $changed[0]['nama'] = 'Nama baru';
    $changed[0]['noinven'] = 'NOMOR-BARU';
    Http::fake(['aset.test/*' => Http::sequence()->push(['success' => true, 'data' => $rows])->push(['success' => true, 'data' => [$changed[0]]])->push(['success' => true, 'data' => $changed])]);
    $this->actingAs($user)->postJson('/api/aset-sinkron/jalankan')->assertAccepted();
    $asset = Aset::where('external_id', '250')->firstOrFail();
    $asset->update(['lokasi' => 'Lab lokal', 'deskripsi' => 'Lokal', 'perlu_ditinjau' => false]);
    $rows[0]['nama'] = 'Nama baru';
    $rows[0]['noinven'] = 'NOMOR-BARU';
    $this->postJson('/api/aset-sinkron/jalankan')->assertAccepted()->assertJsonPath('data.jumlah_tidak_ditemukan', 1);
    expect($asset->refresh()->kode_aset)->toBe('API_ASET-250');
    expect($asset->lokasi)->toBe('Lab lokal');
    expect($asset->perlu_ditinjau)->toBeFalse();
    $this->postJson('/api/aset-sinkron/jalankan')->assertAccepted();
    expect(Aset::where('external_id', '251')->firstOrFail()->status)->toBe('AKTIF');
});
test('empty failed and paginated API responses cannot mark assets missing', function (array $body): void {
    $user = asetSyncSetup();
    $source = SumberAset::where('kode', 'API_ASET')->firstOrFail();
    $asset = Aset::factory()->create(['sumber_id' => $source->id]);
    Http::fake(['aset.test/*' => Http::response($body)]);
    $this->actingAs($user)->postJson('/api/aset-sinkron/jalankan')->assertAccepted()->assertJsonPath('data.status', 'GAGAL');
    expect($asset->fresh()->status)->toBe('AKTIF');
})->with([[['success' => true, 'data' => []]], [['success' => false, 'data' => []]], [['success' => true, 'data' => [['idx' => 1]], 'meta' => ['last_page' => 2]]]]);
test('invalid rows are rejected but valid rows continue and existing invalid ids stay present', function (): void {
    $user = asetSyncSetup();
    $rows = array_slice(asetFixture()['data'], 0, 3);
    $rows[1]['stock'] = 0;
    $rows[2]['idx'] = 'bad';
    Http::fake(['aset.test/*' => Http::response(['success' => true, 'data' => $rows])]);
    $this->actingAs($user)->postJson('/api/aset-sinkron/jalankan')->assertAccepted()->assertJsonPath('data.status', 'SEBAGIAN')->assertJsonPath('data.jumlah_ditolak', 2)->assertJsonPath('data.jumlah_dibuat', 1);
});
test('queued sync rejects a concurrent trigger and only super admin may trigger or inspect it', function (): void {
    $user = asetSyncSetup();
    Queue::fake();
    $this->actingAs($user)->postJson('/api/aset-sinkron/jalankan')->assertAccepted();
    $this->postJson('/api/aset-sinkron/jalankan')->assertConflict()->assertJsonPath('code', 'sinkron_sedang_berjalan');
    Queue::assertPushed(App\Jobs\SinkronAset::class, 1);
    $this->actingAs(katalogActor('ADMIN_SATKER'))->postJson('/api/aset-sinkron/jalankan')->assertForbidden();
    $this->getJson('/api/aset-sinkron')->assertForbidden();
    Cache::lock(SinkronAsetService::LOCK)->forceRelease();
});

test('CSV routes and control tree enforce the role matrix', function (string $role, string $resource): void {
    $user = katalogActor($role);
    $this->actingAs($user)->get('/api/'.$resource.'/ekspor')->assertOk();
    $response = $this->postJson('/api/'.$resource.'/impor', ['file' => UploadedFile::fake()->createWithContent('data.csv', 'kode,nama')]);
    if ($role === 'SUPER_ADMIN') {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
    $this->getJson('/api/kontrol/pohon')->assertOk();
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF'])->with(['ancaman', 'kerentanan', 'kontrol']);
test('assignment writes and sync access honor every non-admin role', function (string $role): void {
    $unit = UnitOrganisasi::factory()->create();
    $user = katalogActor($role, $unit);
    $asset = Aset::factory()->create(['unit_pemilik_id' => $unit->id]);
    $assignment = PenugasanAset::factory()->create(['aset_id' => $asset->id]);
    $this->actingAs($user)->getJson('/api/penugasan-aset/'.$assignment->id)->assertOk();
    $this->postJson('/api/penugasan-aset', [])->assertForbidden();
    $this->patchJson('/api/penugasan-aset/'.$assignment->id, [])->assertForbidden();
    $this->deleteJson('/api/penugasan-aset/'.$assignment->id)->assertForbidden();
    if ($role !== 'SUPER_ADMIN') {
        $this->getJson('/api/aset-sinkron')->assertForbidden();
        $this->postJson('/api/aset-sinkron/jalankan')->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'KASATKER', 'KABID', 'KASUB', 'STAFF']);
test('sync failed HTTP retries three times without exposing token or changing assets', function (): void {
    $user = asetSyncSetup();
    $source = SumberAset::where('kode', 'API_ASET')->firstOrFail();
    $asset = Aset::factory()->create(['sumber_id' => $source->id]);
    Http::fake(['aset.test/*' => Http::response(['message' => 'secret-test-token'], 500)]);
    $response = $this->actingAs($user)->postJson('/api/aset-sinkron/jalankan')->assertAccepted()->assertJsonPath('data.status', 'GAGAL');
    Http::assertSentCount(3);
    expect($response->getContent())->not->toContain('secret-test-token');
    expect($asset->fresh()->status)->toBe('AKTIF');
});
test('catalog audit failures roll back the mutation', function (): void {
    $user = katalogActor();
    $type = JenisAncaman::factory()->create(['nama' => 'Awal']);
    $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new RuntimeException('Audit failure'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($user)->patchJson('/api/jenis-ancaman/'.$type->id,['nama' => 'Berubah']))->toThrow(RuntimeException::class);
    expect($type->fresh()->nama)->toBe('Awal');
});
