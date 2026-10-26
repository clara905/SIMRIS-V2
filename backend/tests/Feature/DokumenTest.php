<?php

use App\Contracts\PemindaiBerkas;
use App\Exceptions\BusinessRuleException;
use App\Jobs\PeriksaFileLampiran;
use App\Models\Dokumen;
use App\Models\DokumenVersi;
use App\Models\FileLampiran;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DokumenService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    Storage::fake('lampiran');
    config(['queue.default' => 'sync']);
});
function dokumenActor(string $role = 'STAFF', ?UnitOrganisasi $unit = null): array
{
    $unit ??= UnitOrganisasi::factory()->create();
    $user = User::factory()->create();
    grantRole($user, $role, $role === 'SUPER_ADMIN' ? null : $unit->id);

    return [$user, $unit];
}
function dokumenDraft(UnitOrganisasi $unit, User $user): DokumenVersi
{
    $doc = Dokumen::factory()->create(['unit_pemilik_id' => $unit->id, 'dibuat_oleh' => $user->id]);

    return DokumenVersi::factory()->create(['dokumen_id' => $doc->id, 'dibuat_oleh' => $user->id]);
}
function dokumenPdf(string $name = 'bukti.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
}
test('document endpoint role matrix is enforced', function (string $role): void {
    [$user,$unit] = dokumenActor($role);
    $version = dokumenDraft($unit, $user);
    $this->actingAs($user);
    foreach (['dokumen', 'dokumen-versi', 'file-lampiran'] as $resource) {
        $this->getJson('/api/'.$resource)->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }
    $this->getJson('/api/dokumen/'.$version->dokumen_id)->assertOk();
    $this->getJson('/api/dokumen-versi/'.$version->id)->assertOk();
    $this->getJson('/api/dokumen/'.$version->dokumen_id.'/versi')->assertOk();
    $can = in_array($role, ['STAFF', 'ADMIN_SATKER'], true);
    $response = $this->postJson('/api/dokumen', ['unit_pemilik_id' => $unit->id, 'kode' => 'D-API', 'judul' => 'Dokumen baru', 'jenis' => 'BUKTI']);
    if ($can) {
        $response->assertCreated();
    } else {
        $response->assertForbidden();
    }
    $response = $this->postJson('/api/dokumen/'.$version->dokumen_id.'/versi', ['format_teks' => 'PLAIN', 'isi_teks' => 'Isi']);
    if ($can) {
        $response->assertCreated()->assertJsonPath('data.nomor_versi', 2);
    } else {
        $response->assertForbidden();
    }
    $response = $this->patchJson('/api/dokumen-versi/'.$version->id, ['isi_teks' => 'Isi diubah']);
    if ($can) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
    $response = $this->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => dokumenPdf()]);
    if ($can) {
        $id = $response->assertCreated()->assertJsonPath('data.status_pemeriksaan', 'AMAN')->assertJsonMissingPath('data.storage_key')->json('data.id');
        $this->get('/api/file-lampiran/'.$id.'/unduh')->assertOk();
    } else {
        $response->assertForbidden();
    }
    $response = $this->postJson('/api/dokumen-versi/'.$version->id.'/finalkan');
    if ($can) {
        $response->assertOk()->assertJsonPath('data.status', 'FINAL');
    } else {
        $response->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']);
test('documents and uploads outside scope are hidden with 404', function (): void {
    [$user,$unit] = dokumenActor();
    [$other,$foreign] = dokumenActor();
    $version = dokumenDraft($foreign, $other);
    $this->actingAs($user)->getJson('/api/dokumen/'.$version->dokumen_id)->assertNotFound();
    $this->patchJson('/api/dokumen-versi/'.$version->id, ['isi_teks' => 'X'])->assertNotFound();
    $this->postJson('/api/dokumen-versi/'.$version->id.'/finalkan')->assertNotFound();
    $this->postJson('/api/dokumen/'.$version->dokumen_id.'/versi', ['format_teks' => 'PLAIN'])->assertNotFound();
    $this->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => dokumenPdf()])->assertNotFound();
});
test('final document version and attachments cannot be changed through API or model', function (): void {
    [$user,$unit] = dokumenActor();
    $version = dokumenDraft($unit, $user);
    $file = $this->actingAs($user)->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => dokumenPdf()])->assertCreated()->json('data.id');
    $stale = DokumenVersi::findOrFail($version->id);
    $this->postJson('/api/dokumen-versi/'.$version->id.'/finalkan')->assertOk();
    $this->patchJson('/api/dokumen-versi/'.$version->id, ['isi_teks' => 'Ganti'])->assertConflict()->assertJsonPath('code', 'dokumen_final');
    $this->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => dokumenPdf()])->assertConflict();
    $this->deleteJson('/api/dokumen-versi/'.$version->id.'/file/'.$file)->assertConflict();
    expect(fn () => $stale->update(['isi_teks' => 'Lewat model']))->toThrow(BusinessRuleException::class);
    expect(fn () => FileLampiran::findOrFail($file)->update(['storage_key' => 'lain']))->toThrow(BusinessRuleException::class);
    expect(fn () => FileLampiran::findOrFail($file)->delete())->toThrow(BusinessRuleException::class);
    $this->postJson('/api/dokumen/'.$version->dokumen_id.'/versi', ['format_teks' => 'PLAIN', 'isi_teks' => 'Versi baru'])->assertCreated()->assertJsonPath('data.status', 'DRAFT');
});
test('pending files cannot be downloaded or finalized and double extensions are rejected by scanner', function (): void {
    [$user,$unit] = dokumenActor();
    $version = dokumenDraft($unit, $user);
    Queue::fake();
    $file = $this->actingAs($user)->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => dokumenPdf('bukti.php.pdf')])->assertCreated()->assertJsonPath('data.status_pemeriksaan', 'PENDING')->json('data.id');
    Queue::assertPushed(PeriksaFileLampiran::class);
    $this->getJson('/api/file-lampiran/'.$file.'/unduh')->assertConflict();
    $this->postJson('/api/dokumen-versi/'.$version->id.'/finalkan')->assertConflict()->assertJsonPath('code', 'lampiran_belum_aman');
    (new PeriksaFileLampiran($file))->handle(app(PemindaiBerkas::class));
    expect(FileLampiran::findOrFail($file)->status_pemeriksaan)->toBe('DITOLAK');
    $this->getJson('/api/file-lampiran/'.$file.'/unduh')->assertConflict();
    $this->deleteJson('/api/dokumen-versi/'.$version->id.'/file/'.$file)->assertOk();
    $this->postJson('/api/dokumen-versi/'.$version->id.'/finalkan')->assertOk();
});
test('file content MIME extension and size are checked before storage', function (): void {
    [$user,$unit] = dokumenActor();
    $version = dokumenDraft($unit, $user);
    $this->actingAs($user);
    foreach ([UploadedFile::fake()->createWithContent('malware.exe', 'MZfake'), UploadedFile::fake()->createWithContent('fake.pdf', 'Plain text pretending to be PDF')] as $file) {
        $this->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
    }
    config(['lampiran.max_kb' => 1]);
    $this->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => UploadedFile::fake()->create('besar.pdf', 2, 'application/pdf')])->assertUnprocessable();
    expect(Storage::disk('lampiran')->allFiles())->toBe([]);
    $this->assertDatabaseCount('FILE_LAMPIRAN', 0);
});
test('empty document cannot be final and only a FINAL version can be used as evidence', function (): void {
    [$user,$unit] = dokumenActor();
    $version = dokumenDraft($unit, $user);
    $version->update(['isi_teks' => null]);
    $this->actingAs($user)->postJson('/api/dokumen-versi/'.$version->id.'/finalkan')->assertConflict()->assertJsonPath('code', 'dokumen_kosong');
    expect(fn () => DB::transaction(fn () => app(DokumenService::class)->finalVersion($version->id, $user)))->toThrow(BusinessRuleException::class);
    $this->patchJson('/api/dokumen-versi/'.$version->id, ['isi_teks' => 'Isi FINAL'])->assertOk();
    $this->postJson('/api/dokumen-versi/'.$version->id.'/finalkan')->assertOk();
    expect(DB::transaction(fn () => app(DokumenService::class)->finalVersion($version->id, $user))->status)->toBe('FINAL');
});
test('upload audit failure rolls back metadata links and private file', function (): void {
    [$user,$unit] = dokumenActor();
    $version = dokumenDraft($unit, $user);
    $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new RuntimeException('Audit failure'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($user)->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => dokumenPdf()]))->toThrow(RuntimeException::class);
    expect(Storage::disk('lampiran')->allFiles())->toBe([]);
    $this->assertDatabaseCount('FILE_LAMPIRAN', 0);
    $this->assertDatabaseCount('DOKUMEN_VERSI_FILE', 0);
});
test('download checks document scope and detects changed bytes', function (): void {
    [$user,$unit] = dokumenActor();
    $version = dokumenDraft($unit, $user);
    $id = $this->actingAs($user)->postJson('/api/file-lampiran', ['dokumen_versi_id' => $version->id, 'file' => dokumenPdf()])->assertCreated()->json('data.id');
    [$other] = dokumenActor();
    $this->actingAs($other)->getJson('/api/file-lampiran/'.$id.'/unduh')->assertNotFound();
    $file = FileLampiran::findOrFail($id);
    Storage::disk('lampiran')->put($file->storage_key, 'modified');
    $this->actingAs($user)->getJson('/api/file-lampiran/'.$id.'/unduh')->assertConflict()->assertJsonPath('code', 'integritas_berkas');
});
test('document routes require authentication and forbid status injection', function (): void {
    foreach (['/api/dokumen', '/api/dokumen-versi', '/api/file-lampiran'] as $url) {
        $this->getJson($url)->assertUnauthorized();
    }
    [$user,$unit] = dokumenActor();
    $version = dokumenDraft($unit, $user);
    $this->actingAs($user)->patchJson('/api/dokumen-versi/'.$version->id, ['status' => 'FINAL'])->assertUnprocessable();
    $this->postJson('/api/dokumen/'.$version->dokumen_id.'/versi',['format_teks' => 'PLAIN', 'status' => 'FINAL'])->assertUnprocessable();
    $this->getJson('/api/dokumen?per_page=101')->assertUnprocessable();
});
