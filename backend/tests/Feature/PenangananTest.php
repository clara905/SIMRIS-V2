<?php

use App\Exceptions\BusinessRuleException;
use App\Models\AsesmenRisiko;
use App\Models\Dokumen;
use App\Models\DokumenVersi;
use App\Models\FileLampiran;
use App\Models\KeputusanRisiko;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\PenilaianRisiko;
use App\Models\PeriodePenilaian;
use App\Models\Risiko;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UsulanKeputusan;
use App\Services\AuditService;
use App\Services\PenangananService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

function penangananFixture(string $decision = 'MITIGASI', string $kind = 'SUBBIDANG'): array
{
    $satker = UnitOrganisasi::factory()->create(['jenis' => 'SATKER']);
    $bidang = UnitOrganisasi::factory()->create(['jenis' => 'BIDANG', 'parent_id' => $satker->id]);
    $sub = UnitOrganisasi::factory()->create(['jenis' => 'SUBBIDANG', 'parent_id' => $bidang->id]);
    $unit = match ($kind) {
        'SATKER' => $satker,'BIDANG' => $bidang,default => $sub
    };
    $actors = [];
    foreach (['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER'] as $role) {
        $user = User::factory()->create();
        grantRole($user, $role, match ($role) {
            'SUPER_ADMIN' => null,'KASATKER','ADMIN_SATKER' => $satker->id,'KABID' => $bidang->id,default => $unit->id
        });
        $actors[$role] = $user;
    }
    $period = PeriodePenilaian::factory()->create(['tanggal_mulai' => today()->subMonth(), 'tanggal_selesai' => today()->addMonth()]);
    $assessment = AsesmenRisiko::factory()->create(['unit_id' => $unit->id, 'dibuat_oleh' => $actors['STAFF']->id, 'periode_id' => $period->id]);
    $risk = Risiko::factory()->create(['asesmen_id' => $assessment->id, 'dibuat_oleh' => $actors['STAFF']->id]);
    $current = PenilaianRisiko::factory()->create(['risiko_id' => $risk->id, 'tahap' => 'SAAT_INI', 'dinilai_oleh' => $actors['STAFF']->id]);
    PenilaianRisiko::factory()->create(['risiko_id' => $risk->id, 'tahap' => 'TARGET_SETELAH', 'dinilai_oleh' => $actors['STAFF']->id]);
    UsulanKeputusan::factory()->create(['penilaian_id' => $current->id, 'diusulkan_oleh' => $actors['STAFF']->id, 'keputusan' => $decision]);

    return compact('satker', 'bidang', 'sub', 'unit', 'actors', 'assessment', 'risk', 'current');
}

function penangananReady(array $f): array
{
    $id = $f['assessment']->id;
    $actor = $f['actors']['STAFF'];
    $employee = Pegawai::factory()->create();
    $actor->update(['pegawai_id' => $employee->id]);
    PenempatanPegawai::factory()->create(['pegawai_id' => $employee->id, 'unit_id' => $f['unit']->id]);
    DB::table('ASESMEN_RISIKO')->where('id', $id)->update(['status' => 'PENANGANAN', 'dibekukan_awal_pada' => now()]);
    $plan = app(PenangananService::class)->plan($id, null, ['pic_id' => $employee->id, 'kode' => 'P-1', 'uraian' => 'Pemulihan layanan', 'target_selesai' => today()->addWeek()->toDateString(), 'risiko_ids' => [$f['risk']->id]], $actor);
    $doc = Dokumen::factory()->create(['unit_pemilik_id' => $f['unit']->id]);
    $version = DokumenVersi::factory()->create(['dokumen_id' => $doc->id]);
    $version->update(['status' => 'FINAL']);

    return [$plan, $version];
}
test('mitigation journey records evidence residual approvals and final decision then freezes every write', function (): void {
    $f = penangananFixture();
    [$plan,$version] = penangananReady($f);
    $id = $f['assessment']->id;
    $risk = $f['risk']->id;
    $this->actingAs($f['actors']['STAFF'])->postJson('/api/asesmen/'.$id.'/selesai-penanganan')->assertConflict();
    $progress = $this->postJson('/api/rencana-penanganan/'.$plan->id.'/progres', ['uraian' => 'Pekerjaan selesai', 'status' => 'SELESAI'])->assertCreated()->json('data.id');
    $this->postJson('/api/progres/'.$progress.'/bukti', ['dokumen_versi_id' => $version->id, 'keterangan' => 'Bukti selesai'])->assertCreated();
    $this->postJson('/api/asesmen/'.$id.'/selesai-penanganan')->assertOk()->assertJsonPath('data.status', 'RESIDUAL_DRAFT');
    $this->patchJson('/api/risiko/'.$risk, ['skenario' => 'Ubah beku'])->assertConflict();
    $payload = ['tahap' => 'AKTUAL_SETELAH', 'matriks_id' => $f['current']->matriks_id, 'alasan_kemungkinan' => 'Teruji', 'alasan_dampak' => 'Terukur', 'kesimpulan' => 'Tercapai'];
    $actual = $this->postJson('/api/risiko/'.$risk.'/penilaian', $payload)->assertCreated()->json('data.id');
    $this->postJson('/api/asesmen/'.$id.'/ajukan-residual')->assertConflict();
    $this->postJson('/api/penilaian/'.$actual.'/bukti', ['dokumen_versi_id' => $version->id, 'keterangan' => 'Bukti residual'])->assertCreated();
    $this->getJson('/api/risiko/'.$risk)->assertOk()->assertJsonPath('data.residual_tercapai', true);
    $this->postJson('/api/asesmen/'.$id.'/ajukan-residual')->assertOk()->assertJsonPath('data.status', 'VERIFIKASI_KASUB');
    foreach (['KASUB' => 'VERIFIKASI_KABID', 'KABID' => 'MENUNGGU_KASATKER'] as $role => $next) {
        $this->actingAs($f['actors'][$role])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => $role.'_RESIDUAL', 'keputusan' => 'SETUJU'])->assertOk()->assertJsonPath('data.status', $next);
    }
    $this->actingAs($f['actors']['KASATKER'])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KASATKER_FINAL', 'keputusan' => 'SETUJU'])->assertForbidden();
    $this->withHeader('Origin', 'http://localhost:5173')->withSession(['auth.password_confirmed_at' => now()->timestamp])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KASATKER_FINAL', 'keputusan' => 'SETUJU', 'catatan' => 'Disahkan'])->assertOk()->assertJsonPath('data.status', 'DISAHKAN');
    $this->assertDatabaseHas('KEPUTUSAN_RISIKO', ['penilaian_id' => $actual, 'keputusan' => 'MITIGASI', 'diputuskan_oleh' => $f['actors']['KASATKER']->id, 'alasan' => 'Disahkan']);
    $this->flushSession();
    $this->actingAs($f['actors']['STAFF'])->postJson('/api/risiko/'.$risk.'/penilaian', $payload)->assertConflict();
    $this->patchJson('/api/rencana-penanganan/'.$plan->id, ['uraian' => 'Tidak boleh'])->assertConflict();
    $this->postJson('/api/rencana-penanganan/'.$plan->id.'/progres', ['uraian' => 'Tidak boleh', 'status' => 'BERJALAN'])->assertConflict();
    $this->postJson('/api/progres/'.$progress.'/bukti', ['dokumen_versi_id' => $version->id, 'keterangan' => 'Tidak boleh'])->assertConflict();
});
test('non mitigation final approval anchors current rating and rejection freezes for revision', function (string $action): void {
    $f = penangananFixture('TERIMA');
    $id = $f['assessment']->id;
    DB::table('ASESMEN_RISIKO')->where('id', $id)->update(['status' => 'MENUNGGU_KASATKER', 'dibekukan_awal_pada' => now()]);
    $this->actingAs($f['actors']['KASATKER'])->withHeader('Origin', 'http://localhost:5173')->withSession(['auth.password_confirmed_at' => now()->timestamp])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KASATKER_FINAL', 'keputusan' => $action, 'catatan' => 'Keputusan akhir'])->assertOk()->assertJsonPath('data.status', $action === 'SETUJU' ? 'DISAHKAN' : 'DITOLAK');
    if ($action === 'SETUJU') {
        $this->assertDatabaseHas('KEPUTUSAN_RISIKO', ['penilaian_id' => $f['current']->id, 'keputusan' => 'TERIMA']);
    } else {
        $this->assertDatabaseCount('KEPUTUSAN_RISIKO', 0);
    }
})->with(['SETUJU', 'TOLAK']);
test('progress and planning honor all roles with read scope', function (string $role): void {
    $f = penangananFixture();
    [$plan,$version] = penangananReady($f);
    $this->actingAs($f['actors'][$role])->getJson('/api/rencana-penanganan/'.$plan->id)->assertOk();
    $response = $this->postJson('/api/rencana-penanganan/'.$plan->id.'/progres', ['uraian' => 'Laporan', 'status' => 'BERJALAN']);
    if (in_array($role, ['STAFF', 'ADMIN_SATKER'], true)) {
        $response->assertCreated();
    } else {
        $response->assertForbidden();
    }
    $response = $this->patchJson('/api/rencana-penanganan/'.$plan->id, ['uraian' => 'Perbaikan']);
    if (in_array($role, ['STAFF', 'ADMIN_SATKER'], true)) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER']);
test('residual rejection requires return destination and a fresh immutable rating', function (string $role, string $state, string $stage): void {
    $f = penangananFixture();
    [$plan,$version] = penangananReady($f);
    $id = $f['assessment']->id;
    DB::table('ASESMEN_RISIKO')->where('id', $id)->update(['status' => 'RESIDUAL_DRAFT']);
    $rating = PenilaianRisiko::factory()->create(['risiko_id' => $f['risk']->id, 'tahap' => 'AKTUAL_SETELAH', 'dinilai_oleh' => $f['actors']['STAFF']->id]);
    app(PenangananService::class)->evidence('penilaian', $rating->id, ['dokumen_versi_id' => $version->id, 'keterangan' => 'Bukti'], $f['actors']['STAFF']);
    DB::table('ASESMEN_RISIKO')->where('id', $id)->update(['status' => $state]);
    $payload = ['tahap' => $stage, 'keputusan' => 'TOLAK', 'catatan' => 'Perbaiki hasil'];
    $this->actingAs($f['actors'][$role])->postJson('/api/asesmen/'.$id.'/review', $payload)->assertConflict()->assertJsonPath('code', 'kembali_ke_wajib');
    $this->postJson('/api/asesmen/'.$id.'/review', [...$payload, 'kembali_ke' => 'RESIDUAL_DRAFT'])->assertOk()->assertJsonPath('data.status', 'RESIDUAL_DRAFT');
    $this->actingAs($f['actors']['STAFF'])->postJson('/api/asesmen/'.$id.'/ajukan-residual')->assertConflict();
    expect(fn () => $rating->update(['kesimpulan' => 'Ubah riwayat']))->toThrow(BusinessRuleException::class);
    $new = $this->postJson('/api/risiko/'.$f['risk']->id.'/penilaian', ['tahap' => 'AKTUAL_SETELAH', 'matriks_id' => $rating->matriks_id, 'alasan_kemungkinan' => 'Baru', 'alasan_dampak' => 'Baru', 'kesimpulan' => 'Baru'])->assertCreated()->json('data.id');
    $this->postJson('/api/penilaian/'.$new.'/bukti', ['dokumen_versi_id' => $version->id, 'keterangan' => 'Baru'])->assertCreated();
    $this->postJson('/api/asesmen/'.$id.'/ajukan-residual')->assertOk();
    expect(PenilaianRisiko::where('risiko_id', $f['risk']->id)->where('tahap', 'AKTUAL_SETELAH')->count())->toBe(2);
})->with([['KASUB', 'VERIFIKASI_KASUB', 'KASUB_RESIDUAL'], ['KABID', 'VERIFIKASI_KABID', 'KABID_RESIDUAL'], ['KASATKER', 'MENUNGGU_KASATKER', 'KASATKER_FINAL']]);

test('residual reviewers reject wrong roles and preserve state', function (string $role, string $state, string $stage, string $allowed): void {
    $f = penangananFixture();
    DB::table('ASESMEN_RISIKO')->where('id', $f['assessment']->id)->update(['status' => $state, 'dibekukan_awal_pada' => now()]);
    $response = $this->actingAs($f['actors'][$role])->postJson('/api/asesmen/'.$f['assessment']->id.'/review', ['tahap' => $stage, 'keputusan' => 'TOLAK', 'catatan' => 'Perbaiki', 'kembali_ke' => 'PENANGANAN']);
    if ($role === $allowed) {
        $response->assertOk()->assertJsonPath('data.status', 'PENANGANAN');
    } else {
        $response->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER'])->with([['VERIFIKASI_KASUB', 'KASUB_RESIDUAL', 'KASUB'], ['VERIFIKASI_KABID', 'KABID_RESIDUAL', 'KABID'], ['MENUNGGU_KASATKER', 'KASATKER_FINAL', 'KASATKER']]);
test('foreign unit ids remain hidden and same unit non PIC cannot report', function (): void {
    $f = penangananFixture();
    [$plan,$version] = penangananReady($f);
    $outsider = User::factory()->create();
    grantRole($outsider, 'ADMIN_SATKER', UnitOrganisasi::factory()->create()->id);
    $this->actingAs($outsider)->getJson('/api/rencana-penanganan/'.$plan->id)->assertNotFound();
    $this->postJson('/api/rencana-penanganan/'.$plan->id.'/progres', ['uraian' => 'X', 'status' => 'SELESAI'])->assertNotFound();
    $staff = User::factory()->create();
    grantRole($staff, 'STAFF', $f['unit']->id);
    $this->actingAs($staff)->getJson('/api/rencana-penanganan/'.$plan->id)->assertOk();
    $this->postJson('/api/rencana-penanganan/'.$plan->id.'/progres', ['uraian' => 'X', 'status' => 'SELESAI'])->assertForbidden();
});
test('evidence rejects drafts and final documents with unsafe attachments without pivot writes', function (): void {
    $f = penangananFixture();
    [$plan,$version] = penangananReady($f);
    $progress = app(PenangananService::class)->report($plan->id, ['uraian' => 'Selesai', 'status' => 'SELESAI'], $f['actors']['STAFF']);
    $draft = DokumenVersi::factory()->create(['dokumen_id' => $version->dokumen_id, 'nomor_versi' => 2]);
    $url = '/api/progres/'.$progress->id.'/bukti';
    $this->actingAs($f['actors']['STAFF'])->postJson($url, ['dokumen_versi_id' => $draft->id, 'keterangan' => 'Draft'])->assertConflict();
    foreach (['PENDING', 'DITOLAK'] as $state) {
        $file = FileLampiran::factory()->create(['status_pemeriksaan' => $state]);
        DB::table('DOKUMEN_VERSI_FILE')->insert(['dokumen_versi_id' => $version->id, 'file_id' => $file->id, 'urutan' => 1, 'keterangan' => 'Uji berkas tidak aman', 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson($url, ['dokumen_versi_id' => $version->id, 'keterangan' => 'Belum aman'])->assertConflict();
        DB::table('DOKUMEN_VERSI_FILE')->where('file_id', $file->id)->delete();
    }
    $this->assertDatabaseCount('BUKTI_PENANGANAN', 0);
});
test('failed final audit rolls back decisions reviews and freeze atomically', function (): void {
    $f = penangananFixture('TERIMA');
    $id = $f['assessment']->id;
    DB::table('ASESMEN_RISIKO')->where('id', $id)->update(['status' => 'MENUNGGU_KASATKER', 'dibekukan_awal_pada' => now()]);
    $this->mock(AuditService::class, function ($mock): void {
        $mock->shouldReceive('record')->andThrow(new RuntimeException('Simulasi audit gagal'));
    });
    $this->actingAs($f['actors']['KASATKER'])->withHeader('Origin', 'http://localhost:5173')->withSession(['auth.password_confirmed_at' => now()->timestamp])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KASATKER_FINAL', 'keputusan' => 'SETUJU'])->assertStatus(500);
    $this->assertDatabaseCount('KEPUTUSAN_RISIKO', 0);
    $this->assertDatabaseCount('REVIEW_ASESMEN', 0);
    expect($f['assessment']->fresh()->status)->toBe('MENUNGGU_KASATKER');
    expect($f['assessment']->fresh()->dibekukan_pada)->toBeNull();
});

test('final decision factory creates a valid immutable decision fixture', function (): void {
    $decision = KeputusanRisiko::factory()->create();
    expect($decision->penilaian_id)->toBeInt();
    expect(fn () => $decision->update(['alasan' => 'Timpa']))->toThrow(BusinessRuleException::class);
});
