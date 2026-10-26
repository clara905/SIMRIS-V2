<?php

use App\Models\AsesmenAset;
use App\Models\AsesmenRisiko;
use App\Models\PenilaianRisiko;
use App\Models\PeriodePenilaian;
use App\Models\ReviewAsesmen;
use App\Models\Risiko;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UsulanKeputusan;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);
function workflowFixture(string $decision = 'MITIGASI', string $kind = 'SUBBIDANG'): array
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
test('each initial transition reaches its configured status and writes audit', function (string $from, string $action, string $role, string $stage, string $expected, string $decision): void {
    $f = workflowFixture($decision);
    DB::table('ASESMEN_RISIKO')->where('id', $f['assessment']->id)->update(['status' => $from]);
    $id = $f['assessment']->id;
    $url = '/api/asesmen/'.$id.'/'.($action === 'AJUKAN' ? 'ajukan' : 'review');
    $payload = $action === 'AJUKAN' ? [] : ['tahap' => $stage, 'keputusan' => $action, 'catatan' => 'Catatan keputusan'];
    $this->actingAs($f['actors'][$role])->postJson($url, $payload)->assertOk()->assertJsonPath('data.status', $expected);
    $assessment = $f['assessment']->fresh();
    if (in_array($expected, ['PENANGANAN', 'MENUNGGU_KASATKER'], true)) {
        expect($assessment->dibekukan_awal_pada)->not->toBeNull();
    } if ($expected === 'DITOLAK') {
        expect($assessment->dibekukan_pada)->not->toBeNull();
    }
    if ($action !== 'AJUKAN') {
        $this->assertDatabaseHas('REVIEW_ASESMEN', ['asesmen_id' => $id, 'reviewer_id' => $f['actors'][$role]->id, 'tahap' => $stage, 'keputusan' => $action]);
    }
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'ASESMEN_RISIKO', 'entitas_id' => $id, 'aksi' => $action === 'SETUJU' ? 'APPROVE' : ($action === 'TOLAK' ? 'REJECT' : 'UPDATE')]);
})->with([
    ['DRAFT', 'AJUKAN', 'STAFF', '', 'MENUNGGU_KASUB', 'MITIGASI'],
    ['MENUNGGU_KASUB', 'SETUJU', 'KASUB', 'KASUB_AWAL', 'MENUNGGU_KABID', 'MITIGASI'],
    ['MENUNGGU_KASUB', 'TOLAK', 'KASUB', 'KASUB_AWAL', 'DITOLAK', 'MITIGASI'],
    ['MENUNGGU_KABID', 'SETUJU', 'KABID', 'KABID_AWAL', 'PENANGANAN', 'MITIGASI'],
    ['MENUNGGU_KABID', 'SETUJU', 'KABID', 'KABID_AWAL', 'MENUNGGU_KASATKER', 'TERIMA'],
    ['MENUNGGU_KABID', 'TOLAK', 'KABID', 'KABID_AWAL', 'DITOLAK', 'MITIGASI'],
]);
test('initial review rejects every wrong role', function (string $role, string $state, string $stage, string $expectedRole): void {
    $f = workflowFixture();
    DB::table('ASESMEN_RISIKO')->where('id', $f['assessment']->id)->update(['status' => $state]);
    if ($role === $expectedRole) {
        $this->actingAs($f['actors'][$role])->postJson('/api/asesmen/'.$f['assessment']->id.'/review', ['tahap' => $stage, 'keputusan' => 'SETUJU'])->assertOk();
    } else {
        $this->actingAs($f['actors'][$role])->postJson('/api/asesmen/'.$f['assessment']->id.'/review', ['tahap' => $stage, 'keputusan' => 'SETUJU'])->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER'])->with([['MENUNGGU_KASUB', 'KASUB_AWAL', 'KASUB'], ['MENUNGGU_KABID', 'KABID_AWAL', 'KABID']]);
test('review route hides foreign units and denies wrong hierarchical role levels', function (): void {
    $f = workflowFixture();
    DB::table('ASESMEN_RISIKO')->where('id', $f['assessment']->id)->update(['status' => 'MENUNGGU_KASUB']);
    $user = User::factory()->create();
    grantRole($user, 'KASUB', UnitOrganisasi::factory()->create()->id);
    $this->actingAs($user)->postJson('/api/asesmen/'.$f['assessment']->id.'/review', ['tahap' => 'KASUB_AWAL', 'keputusan' => 'SETUJU'])->assertNotFound();
    grantRole($user, 'KASUB', $f['satker']->id);
    $this->postJson('/api/asesmen/'.$f['assessment']->id.'/review', ['tahap' => 'KASUB_AWAL', 'keputusan' => 'SETUJU'])->assertForbidden();
});
test('submission requires all initial ratings and proposals and invalid transitions return 409', function (): void {
    $f = workflowFixture();
    UsulanKeputusan::where('penilaian_id', $f['current']->id)->firstOrFail()->delete();
    $this->actingAs($f['actors']['STAFF'])->postJson('/api/asesmen/'.$f['assessment']->id.'/ajukan')->assertConflict()->assertJsonPath('code', 'asesmen_belum_lengkap');
    $this->actingAs($f['actors']['KASUB'])->postJson('/api/asesmen/'.$f['assessment']->id.'/review', ['tahap' => 'KASUB_AWAL', 'keputusan' => 'SETUJU'])->assertConflict()->assertJsonPath('code', 'transisi_tidak_valid');
});
test('review enforces required rejection notes and separation of duties', function (): void {
    $f = workflowFixture();
    $id = $f['assessment']->id;
    DB::table('ASESMEN_RISIKO')->where('id', $id)->update(['status' => 'MENUNGGU_KASUB']);
    $this->actingAs($f['actors']['KASUB'])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KASUB_AWAL', 'keputusan' => 'TOLAK'])->assertConflict()->assertJsonPath('code', 'catatan_wajib');
    grantRole($f['actors']['STAFF'], 'KASUB', $f['sub']->id);
    $this->actingAs($f['actors']['STAFF'])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KASUB_AWAL', 'keputusan' => 'SETUJU'])->assertConflict()->assertJsonPath('code', 'pemisahan_tugas');
    $this->actingAs($f['actors']['KASUB'])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KASUB_AWAL', 'keputusan' => 'SETUJU'])->assertOk();
    grantRole($f['actors']['KASUB'], 'KABID', $f['bidang']->id);
    $this->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KABID_AWAL', 'keputusan' => 'SETUJU'])->assertConflict()->assertJsonPath('code', 'pemisahan_tugas');
});
test('review skips are driven by unit configuration', function (string $kind, string $decision, string $expected): void {
    $f = workflowFixture($decision, $kind);
    $this->actingAs($f['actors']['STAFF'])->postJson('/api/asesmen/'.$f['assessment']->id.'/ajukan')->assertOk()->assertJsonPath('data.status', $expected);
})->with([['BIDANG', 'MITIGASI', 'MENUNGGU_KABID'], ['SATKER', 'MITIGASI', 'PENANGANAN'], ['SATKER', 'TERIMA', 'MENUNGGU_KASATKER']]);
test('rejection revision remaps all draft children and leaves rejected version immutable', function (): void {
    $f = workflowFixture();
    $id = $f['assessment']->id;
    $snapshot = AsesmenAset::factory()->create(['asesmen_id' => $id]);
    DB::table('RISIKO_ASET')->insert(['risiko_id' => $f['risk']->id, 'asesmen_aset_id' => $snapshot->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('ASESMEN_RISIKO')->where('id', $id)->update(['status' => 'MENUNGGU_KASUB']);
    $this->actingAs($f['actors']['KASUB'])->postJson('/api/asesmen/'.$id.'/review', ['tahap' => 'KASUB_AWAL', 'keputusan' => 'TOLAK', 'catatan' => 'Perbaiki'])->assertOk();
    $new = $this->actingAs($f['actors']['STAFF'])->postJson('/api/asesmen/'.$id.'/buat-revisi')->assertCreated()->assertJsonPath('data.nomor_versi', 2)->assertJsonPath('data.versi_sebelumnya_id', $id)->assertJsonPath('data.status', 'DRAFT')->json('data.id');
    $risk = Risiko::where('asesmen_id', $new)->firstOrFail();
    expect($risk->id)->not->toBe($f['risk']->id);
    expect(PenilaianRisiko::where('risiko_id', $risk->id)->count())->toBe(2);
    $newSnapshot = AsesmenAset::where('asesmen_id', $new)->firstOrFail();
    $this->assertDatabaseHas('RISIKO_ASET', ['risiko_id' => $risk->id, 'asesmen_aset_id' => $newSnapshot->id]);
    expect($f['assessment']->fresh()->status)->toBe('DITOLAK');
    $this->patchJson('/api/risiko/'.$f['risk']->id, ['skenario' => 'Tidak boleh'])->assertConflict();
    $this->postJson('/api/asesmen/'.$id.'/buat-revisi')->assertConflict()->assertJsonPath('code', 'seri_aktif_sudah_ada');
    $this->getJson('/api/asesmen/'.$id.'/riwayat-versi')->assertOk()->assertJsonCount(2, 'data');
});
test('review audit failure rolls back both status and review row', function (): void {
    $f = workflowFixture();
    $id = $f['assessment']->id;
    DB::table('ASESMEN_RISIKO')->where('id', $id)->update(['status' => 'MENUNGGU_KASUB']);
    $real = new AuditService;
    $this->mock(AuditService::class)->shouldReceive('record')->andReturnUsing(function (string $table, int $id, string $action, ?array $before = null, ?array $after = null, ?int $actor = null) use ($real): void {
        if ($action === 'APPROVE') {
            throw new RuntimeException('Audit approval failed');
        } $real->record($table, $id, $action, $before, $after, $actor);
    });
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($f['actors']['KASUB'])->postJson('/api/asesmen/'.$id.'/review',['tahap' => 'KASUB_AWAL', 'keputusan' => 'SETUJU']))->toThrow(RuntimeException::class);
    expect($f['assessment']->fresh()->status)->toBe('MENUNGGU_KASUB');
    expect(ReviewAsesmen::count())->toBe(0);
});
