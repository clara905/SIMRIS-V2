<?php

use App\Exceptions\BusinessRuleException;
use App\Models\AreaDampak;
use App\Models\KriteriaDampak;
use App\Models\MatriksRisiko;
use App\Models\PeriodePenilaian;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\TingkatRisiko;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\VersiMetodeRisiko;
use App\Services\AuditService;
use App\Services\MetodeRisikoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    $this->withHeader('Origin', 'http://localhost:5173');
});
function metodeActor(string $role = 'SUPER_ADMIN'): User
{
    $user = User::factory()->create();
    grantRole($user, $role, $role === 'SUPER_ADMIN' ? null : UnitOrganisasi::factory()->create()->id);

    return $user;
}
/** A complete editable 2x2 method, with both criterion directions. */
function metodeFixture(): array
{
    $version = VersiMetodeRisiko::factory()->create();
    $area = AreaDampak::firstOrCreate(['kode' => 'OPERASIONAL'], ['nama' => 'Operasional']);
    $level = TingkatRisiko::factory()->create(['versi_metode_id' => $version->id, 'kode' => 'RENDAH', 'nama' => 'Rendah', 'warna' => '#00AA11']);
    $possibilities = $impacts = [];
    foreach ([1, 2] as $value) {
        $possibilities[] = SkalaKemungkinan::factory()->create(['versi_metode_id' => $version->id, 'nilai' => $value, 'label' => 'Kemungkinan '.$value, 'label_persentase' => 'Persentase '.$value, 'label_frekuensi' => 'Frekuensi '.$value, 'kriteria' => 'Kriteria kemungkinan']);
        $impact = SkalaDampak::factory()->create(['versi_metode_id' => $version->id, 'nilai' => $value, 'label' => 'Dampak '.$value, 'kriteria' => 'Kriteria dampak']);
        $impacts[] = $impact;
        foreach (['P', 'N'] as $direction) {
            KriteriaDampak::factory()->create(['skala_dampak_id' => $impact->id, 'area_dampak_id' => $area->id, 'arah_dampak' => $direction, 'deskripsi' => 'Deskripsi '.$direction]);
        }
    }
    foreach ($possibilities as $possibility) {
        foreach ($impacts as $impact) {
            MatriksRisiko::factory()->create(['versi_metode_id' => $version->id, 'kemungkinan_id' => $possibility->id, 'dampak_id' => $impact->id, 'tingkat_risiko_id' => $level->id, 'rekomendasi_keputusan' => 'TERIMA']);
        }
    }

    return ['versi-metode-risiko' => $version, 'skala-kemungkinan' => $possibilities[0], 'skala-dampak' => $impacts[0], 'area-dampak' => $area, 'kriteria-dampak' => KriteriaDampak::where('skala_dampak_id', $impacts[0]->id)->firstOrFail(), 'tingkat-risiko' => $level, 'matriks-risiko' => MatriksRisiko::where('versi_metode_id', $version->id)->firstOrFail()];
}
test('method endpoints reject unauthenticated requests', function (string $resource): void {
    $this->getJson('/api/'.$resource)->assertUnauthorized();
    $this->postJson('/api/'.$resource)->assertUnauthorized();
    $this->patchJson('/api/'.$resource.'/1')->assertUnauthorized();
    $this->deleteJson('/api/'.$resource.'/1')->assertUnauthorized();
})->with(array_keys(MetodeRisikoService::MODELS));
test('all active roles can list and inspect method masters', function (string $role, string $resource): void {
    $user = metodeActor($role);
    $fixture = metodeFixture();
    $this->actingAs($user)->getJson('/api/'.$resource)->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    $this->getJson('/api/'.$resource.'/'.$fixture[$resource]->id)->assertOk()->assertJsonPath('data.id', $fixture[$resource]->id);
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF'])->with(array_keys(MetodeRisikoService::MODELS));
test('operational roles cannot create edit or delete any method master', function (string $role, string $resource): void {
    $user = metodeActor($role);
    $fixture = metodeFixture();
    $url = '/api/'.$resource.'/'.$fixture[$resource]->id;
    $this->actingAs($user)->postJson('/api/'.$resource, [])->assertForbidden();
    $this->patchJson($url, [])->assertForbidden();
    $this->deleteJson($url)->assertForbidden();
})->with(['ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF'])->with(array_keys(MetodeRisikoService::MODELS));
test('method actions enforce each role permission', function (string $role): void {
    $user = metodeActor($role);
    $fixture = metodeFixture();
    $id = $fixture['versi-metode-risiko']->id;
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    $this->postJson('/api/versi-metode-risiko/'.$id.'/validasi')->assertOk()->assertJsonPath('data.valid', true);
    if ($role !== 'SUPER_ADMIN') {
        $this->postJson('/api/versi-metode-risiko/'.$id.'/bekukan')->assertForbidden();
        $this->postJson('/api/versi-metode-risiko/'.$id.'/duplikasi', ['versi' => 'v2'])->assertForbidden();
        $this->postJson('/api/matriks-risiko/generate', ['versi_metode_id' => $id])->assertForbidden();
    } else {
        $this->postJson('/api/versi-metode-risiko/'.$id.'/bekukan')->assertOk()->assertJsonPath('data.dibekukan', true);
        $this->postJson('/api/versi-metode-risiko/'.$id.'/duplikasi', ['versi' => 'v2'])->assertCreated()->assertJsonPath('data.dibekukan', false);
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']);
test('frozen versions and every child reject updates and deletes', function (string $resource): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $version = $fixture['versi-metode-risiko'];
    $version->update(['dibekukan' => true]);
    $this->actingAs($user)->patchJson('/api/'.$resource.'/'.$fixture[$resource]->id, [])->assertConflict()->assertJsonPath('code', 'versi_sudah_beku');
    $this->deleteJson('/api/'.$resource.'/'.$fixture[$resource]->id)->assertConflict()->assertJsonPath('code', 'versi_sudah_beku');
    expect($fixture[$resource]->fresh())->not->toBeNull();
})->with(array_keys(MetodeRisikoService::MODELS));
test('frozen versions reject creating children through every child endpoint', function (string $resource): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $fixture['versi-metode-risiko']->update(['dibekukan' => true]);
    $payload = $fixture[$resource]->only($fixture[$resource]->getFillable());
    unset($payload['skor']);
    $this->actingAs($user)->postJson('/api/'.$resource, $payload)->assertConflict()->assertJsonPath('code', 'versi_sudah_beku');
})->with(['skala-kemungkinan', 'skala-dampak', 'kriteria-dampak', 'tingkat-risiko', 'matriks-risiko']);
test('observer guards frozen methods despite stale models and parent replacement', function (): void {
    metodeActor();
    $fixture = metodeFixture();
    $stale = $fixture['skala-dampak'];
    $fixture['versi-metode-risiko']->update(['dibekukan' => true]);
    $other = VersiMetodeRisiko::factory()->create();
    expect(fn () => $stale->update(['versi_metode_id' => $other->id]))->toThrow(BusinessRuleException::class);
    expect(fn () => $fixture['area-dampak']->delete())->toThrow(BusinessRuleException::class);
    expect(fn () => $fixture['versi-metode-risiko']->update(['dibekukan' => false]))->toThrow(BusinessRuleException::class);
});
test('freeze requires recent password confirmation and complete method', function (): void {
    $user = metodeActor();
    $version = VersiMetodeRisiko::factory()->create();
    $url = '/api/versi-metode-risiko/'.$version->id.'/bekukan';
    $this->actingAs($user)->postJson($url)->assertForbidden()->assertJsonPath('code', 'konfirmasi_password_diperlukan');
    $this->withSession(['auth.password_confirmed_at' => now()->subMinutes(6)->timestamp])->postJson($url)->assertForbidden();
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])->postJson($url)->assertConflict()->assertJsonPath('code', 'metode_belum_valid')->assertJsonCount(3, 'masalah');
    expect($version->fresh()->dibekukan)->toBeFalse();
});
test('validation reports missing pairs criteria gaps and corrupt scores together', function (): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $version = $fixture['versi-metode-risiko'];
    $fixture['matriks-risiko']->delete();
    DB::table('KRITERIA_DAMPAK')->where('skala_dampak_id', $fixture['skala-dampak']->id)->delete();
    DB::table('SKALA_KEMUNGKINAN')->where('id', $fixture['skala-kemungkinan']->id)->update(['nilai' => 5]);
    $response = $this->actingAs($user)->postJson('/api/versi-metode-risiko/'.$version->id.'/validasi')->assertOk()->assertJsonPath('data.valid', false);
    expect(count($response->json('data.masalah')))->toBeGreaterThanOrEqual(4);
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])->postJson('/api/versi-metode-risiko/'.$version->id.'/bekukan')->assertConflict();
});
test('scale gaps duplicates and deleting a middle value return 409', function (string $resource): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $payload = $fixture[$resource]->only($fixture[$resource]->getFillable());
    $this->actingAs($user)->postJson('/api/'.$resource, $payload)->assertConflict()->assertJsonPath('code', 'skala_tidak_berurutan');
    $payload['nilai'] = 4;
    $this->postJson('/api/'.$resource, $payload)->assertConflict();
    $this->deleteJson('/api/'.$resource.'/'.$fixture[$resource]->id)->assertConflict();
    $payload['nilai'] = 3;
    $new = $this->postJson('/api/'.$resource, $payload)->assertCreated()->json('data.id');
    $this->deleteJson('/api/'.$resource.'/'.$new)->assertOk();
})->with(['skala-kemungkinan', 'skala-dampak']);
test('matrix scores are server calculated and cross-version references are rejected', function (string $field, string $resource): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $other = metodeFixture();
    $cell = $fixture['matriks-risiko'];
    $this->actingAs($user)->patchJson('/api/matriks-risiko/'.$cell->id, [$field => $other[$resource]->id])->assertConflict()->assertJsonPath('code', 'matriks_beda_versi');
    $this->patchJson('/api/matriks-risiko/'.$cell->id, ['skor' => 999])->assertUnprocessable()->assertJsonValidationErrors('skor');
    expect(MatriksRisiko::where('versi_metode_id', $fixture['versi-metode-risiko']->id)->orderBy('id')->pluck('skor')->all())->toBe([1, 2, 2, 4]);
})->with([['kemungkinan_id', 'skala-kemungkinan'], ['dampak_id', 'skala-dampak'], ['tingkat_risiko_id', 'tingkat-risiko']]);
test('generate fills missing pairs without overwriting existing decisions and is idempotent', function (): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $version = $fixture['versi-metode-risiko'];
    $cell = $fixture['matriks-risiko'];
    $cell->update(['rekomendasi_keputusan' => 'MITIGASI']);
    MatriksRisiko::where('versi_metode_id', $version->id)->where('id', '!=', $cell->id)->get()->each->delete();
    $data = ['versi_metode_id' => $version->id, 'tingkat_risiko_id' => $fixture['tingkat-risiko']->id, 'rekomendasi_keputusan' => 'TERIMA'];
    $this->actingAs($user)->postJson('/api/matriks-risiko/generate', $data)->assertOk();
    $this->postJson('/api/matriks-risiko/generate', $data)->assertOk();
    expect(MatriksRisiko::where('versi_metode_id', $version->id)->count())->toBe(4);
    expect($cell->fresh()->rekomendasi_keputusan)->toBe('MITIGASI');
    $version->update(['dibekukan' => true]);
    $this->postJson('/api/matriks-risiko/generate', $data)->assertConflict()->assertJsonPath('code', 'versi_sudah_beku');
});
test('duplicate remaps all child identifiers preserves original and creates editable draft', function (): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $version = $fixture['versi-metode-risiko'];
    $version->update(['dibekukan' => true]);
    $id = $this->actingAs($user)->postJson('/api/versi-metode-risiko/'.$version->id.'/duplikasi', ['versi' => '2027'])->assertCreated()->assertJsonPath('data.dibekukan', false)->json('data.id');
    expect($id)->not->toBe($version->id);
    $this->postJson('/api/versi-metode-risiko/'.$id.'/validasi')->assertOk()->assertJsonPath('data.valid', true);
    expect(SkalaDampak::where('versi_metode_id', $id)->count())->toBe(2);
    expect(KriteriaDampak::whereIn('skala_dampak_id', SkalaDampak::where('versi_metode_id', $id)->select('id'))->count())->toBe(4);
    foreach (MatriksRisiko::where('versi_metode_id', $id)->get() as $cell) {
        expect(SkalaKemungkinan::findOrFail($cell->kemungkinan_id)->versi_metode_id)->toBe($id);
        expect(SkalaDampak::findOrFail($cell->dampak_id)->versi_metode_id)->toBe($id);
        expect(TingkatRisiko::findOrFail($cell->tingkat_risiko_id)->versi_metode_id)->toBe($id);
    }
    $this->patchJson('/api/versi-metode-risiko/'.$id, ['nama' => 'Draft berubah'])->assertOk();
    expect($version->fresh()->dibekukan)->toBeTrue();
    expect(AreaDampak::count())->toBe(1);
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'VERSI_METODE_RISIKO', 'entitas_id' => $id, 'aksi' => 'CREATE', 'user_id' => $user->id]);
});
test('assessment guard rejects draft and accepts frozen versions only inside a transaction', function (): void {
    metodeActor();
    $fixture = metodeFixture();
    $version = $fixture['versi-metode-risiko'];
    $service = app(MetodeRisikoService::class);
    expect(fn () => DB::transaction(fn () => $service->forAssessment($version->id)))->toThrow(BusinessRuleException::class);
    $version->update(['dibekukan' => true]);
    expect(DB::transaction(fn () => $service->forAssessment($version->id))->id)->toBe($version->id);
});
test('new global areas apply to drafts without changing frozen version coverage', function (): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $version = $fixture['versi-metode-risiko'];
    $version->update(['dibekukan' => true]);
    $this->actingAs($user)->postJson('/api/area-dampak', ['kode' => 'BARU', 'nama' => 'Area baru'])->assertCreated();
    $this->postJson('/api/versi-metode-risiko/'.$version->id.'/validasi')->assertOk()->assertJsonPath('data.valid', true);
    $id = $this->postJson('/api/versi-metode-risiko/'.$version->id.'/duplikasi', ['versi' => 'baru'])->assertCreated()->json('data.id');
    $this->postJson('/api/versi-metode-risiko/'.$id.'/validasi')->assertOk()->assertJsonPath('data.valid', false)->assertJsonCount(2, 'data.masalah');
});
test('draft deletion removes children atomically and keeps global areas', function (): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $id = $fixture['versi-metode-risiko']->id;
    $this->actingAs($user)->deleteJson('/api/versi-metode-risiko/'.$id)->assertOk();
    $this->assertDatabaseMissing('VERSI_METODE_RISIKO', ['id' => $id]);
    expect(MatriksRisiko::count())->toBe(0);
    expect(KriteriaDampak::count())->toBe(0);
    expect(AreaDampak::count())->toBe(1);
});
test('audit failure rolls back method mutation', function (): void {
    $user = metodeActor();
    $version = VersiMetodeRisiko::factory()->create(['nama' => 'Sebelum']);
    $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($user)->patchJson('/api/versi-metode-risiko/'.$version->id, ['nama' => 'Sesudah']))->toThrow(RuntimeException::class);
    expect($version->fresh()->nama)->toBe('Sebelum');
});
test('method list validates pagination filters and sorting', function (): void {
    $user = metodeActor();
    VersiMetodeRisiko::factory()->create(['nama' => 'Dicari']);
    VersiMetodeRisiko::factory()->create(['nama' => 'Lain']);
    $this->actingAs($user)->getJson('/api/versi-metode-risiko?filter[nama]=Dicari&sort=-id&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    foreach (['per_page=101', 'filter[unknown]=x', 'sort=id%20desc', 'filter[nama][]=x'] as $query) {
        $this->getJson('/api/versi-metode-risiko?'.$query)->assertUnprocessable();
    }
    $this->getJson('/api/versi-metode-risiko/9999')->assertNotFound();
    $this->postJson('/api/versi-metode-risiko', [])->assertUnprocessable()->assertJsonValidationErrors(['nama', 'versi', 'referensi']);
    $this->postJson('/api/versi-metode-risiko', ['nama' => 'N', 'versi' => '1', 'referensi' => 'R', 'dibekukan' => true])->assertUnprocessable();
});

test('super admin can create update and delete each draft master', function (string $resource): void {
    $user = metodeActor();
    $class = MetodeRisikoService::MODELS[$resource];
    $sample = $class::factory()->create();
    $payload = $sample->only($sample->getFillable());
    unset($payload['skor'],$payload['dibekukan']);
    $sample->delete();
    $this->actingAs($user);
    $id = $this->postJson('/api/'.$resource, $payload)->assertCreated()->json('data.id');
    $field = match ($resource) {
        'versi-metode-risiko','area-dampak','tingkat-risiko' => 'nama','skala-kemungkinan','skala-dampak' => 'label','kriteria-dampak' => 'deskripsi',default => 'rekomendasi_keputusan'
    };
    $this->putJson('/api/'.$resource.'/'.$id, [$field => 'Diperbarui'])->assertOk()->assertJsonPath('data.'.$field, 'Diperbarui');
    $this->assertDatabaseHas((new $class)->getTable(), ['id' => $id, $field => 'Diperbarui']);
    $this->deleteJson('/api/'.$resource.'/'.$id)->assertOk();
    $this->assertDatabaseMissing((new $class)->getTable(), ['id' => $id]);
    foreach (['CREATE', 'UPDATE', 'DELETE'] as $action) {
        $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => (new $class)->getTable(), 'entitas_id' => $id, 'aksi' => $action, 'user_id' => $user->id]);
    }
})->with(array_keys(MetodeRisikoService::MODELS));
test('referenced assessment version cannot be deleted and children remain intact', function (): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $id = $fixture['versi-metode-risiko']->id;
    $series = DB::table('SERI_ASESMEN')->insertGetId(['kode' => 'SERIES', 'nama' => 'Seri tes', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('ASESMEN_RISIKO')->insert(['seri_asesmen_id' => $series, 'periode_id' => PeriodePenilaian::factory()->create()->id, 'unit_id' => UnitOrganisasi::factory()->create()->id, 'versi_metode_id' => $id, 'dibuat_oleh' => $user->id, 'tujuan' => 'Tujuan', 'ruang_lingkup' => 'Lingkup', 'status' => 'DRAFT', 'nomor_versi' => 1, 'dibuat_pada' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($user)->deleteJson('/api/versi-metode-risiko/'.$id)->assertConflict()->assertJsonPath('code', 'versi_dipakai_asesmen');
    expect(MatriksRisiko::where('versi_metode_id', $id)->count())->toBe(4);
    $this->assertDatabaseHas('VERSI_METODE_RISIKO', ['id' => $id]);
});
test('duplicate failure rolls back all newly copied rows and audit entries', function (): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $before = DB::table('LOG_AUDIT')->count();
    $realAudit = new AuditService;
    $this->mock(AuditService::class)->shouldReceive('record')->andReturnUsing(function (string $table, int $id, string $action, ?array $old = null, ?array $new = null, ?int $actor = null) use ($realAudit): void {
        if ($table === 'MATRIKS_RISIKO') {
            throw new RuntimeException('Audit cell unavailable');
        }
        $realAudit->record($table, $id, $action, $old, $new, $actor);
    });
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($user)->postJson('/api/versi-metode-risiko/'.$fixture['versi-metode-risiko']->id.'/duplikasi', ['versi' => 'gagal']))->toThrow(RuntimeException::class);
    expect(VersiMetodeRisiko::count())->toBe(1);
    expect(SkalaDampak::count())->toBe(2);
    expect(DB::table('LOG_AUDIT')->count())->toBe($before);
});
test('duplicate matrix pairs and criterion directions return stable conflict', function (): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $this->actingAs($user);
    foreach (['matriks-risiko', 'kriteria-dampak', 'tingkat-risiko'] as $resource) {
        $payload = $fixture[$resource]->only($fixture[$resource]->getFillable());
        unset($payload['skor']);
        $this->postJson('/api/'.$resource, $payload)->assertConflict()->assertJsonPath('code', 'integritas_data');
    }
    $this->patchJson('/api/kriteria-dampak/'.$fixture['kriteria-dampak']->id, ['arah_dampak' => 'X'])->assertUnprocessable()->assertJsonValidationErrors('arah_dampak');
    $this->patchJson('/api/tingkat-risiko/'.$fixture['tingkat-risiko']->id, ['warna' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('warna');
});
test('draft children cannot be moved into another version', function (string $resource): void {
    $user = metodeActor();
    $fixture = metodeFixture();
    $other = metodeFixture();
    $field = $resource === 'kriteria-dampak' ? 'skala_dampak_id' : 'versi_metode_id';
    $target = $other[$resource === 'kriteria-dampak' ? 'skala-dampak' : 'versi-metode-risiko']->id;
    $this->actingAs($user)->patchJson('/api/'.$resource.'/'.$fixture[$resource]->id, [$field => $target])->assertConflict()->assertJsonPath('code', 'induk_metode_tetap');
})->with(['skala-kemungkinan', 'skala-dampak', 'tingkat-risiko', 'matriks-risiko', 'kriteria-dampak']);
test('generate rejects empty scales and foreign levels before any cell is written', function (): void {
    $user = metodeActor();
    $version = VersiMetodeRisiko::factory()->create();
    $level = TingkatRisiko::factory()->create(['versi_metode_id' => $version->id]);
    $this->actingAs($user)->postJson('/api/matriks-risiko/generate', ['versi_metode_id' => $version->id, 'tingkat_risiko_id' => $level->id, 'rekomendasi_keputusan' => 'TERIMA'])->assertConflict()->assertJsonPath('code', 'skala_belum_lengkap');
    $other = TingkatRisiko::factory()->create();
    $this->postJson('/api/matriks-risiko/generate', ['versi_metode_id' => $version->id, 'tingkat_risiko_id' => $other->id, 'rekomendasi_keputusan' => 'TERIMA'])->assertConflict()->assertJsonPath('code', 'matriks_beda_versi');
    expect(MatriksRisiko::count())->toBe(0);
});
test('method actions require authentication and active roles', function (): void {
    foreach (['/api/matriks-risiko/generate', '/api/versi-metode-risiko/1/bekukan', '/api/versi-metode-risiko/1/duplikasi', '/api/versi-metode-risiko/1/validasi'] as $url) {
        $this->postJson($url)->assertUnauthorized();
    }
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/versi-metode-risiko')->assertForbidden();
});

test('method OpenAPI exposes every CRUD and method action route', function (): void {
    $user = metodeActor();
    $schema = $this->actingAs($user)->getJson('/docs/api.json')->assertOk()->json();
    foreach (array_keys(MetodeRisikoService::MODELS) as $resource) {
        expect($schema['paths']['/api/'.$resource] ?? null)->toHaveKeys(['get', 'post']);
        expect($schema['paths']['/api/'.$resource.'/{id}'] ?? null)->toHaveKeys(['get', 'put', 'delete']);
    }
    foreach (['validasi', 'bekukan', 'duplikasi'] as $action) {
        expect($schema['paths']['/api/versi-metode-risiko/{id}/'.$action] ?? null)->toHaveKey('post');
    }
    expect($schema['paths']['/api/matriks-risiko/generate'] ?? null)->toHaveKey('post');
});
