<?php

use App\Models\AsesmenRisiko;
use App\Models\Dokumen;
use App\Models\DokumenVersi;
use App\Models\Kontrol;
use App\Models\KontrolInternal;
use App\Models\Regulasi;
use App\Models\Risiko;
use App\Models\Soa;
use App\Models\SoaDetail;
use App\Models\SoaVersi;
use App\Models\Sop;
use App\Models\SopVersi;
use App\Models\StandarKontrol;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);
function kepatuhanFixture(): array
{
    $satker = UnitOrganisasi::factory()->create(['jenis' => 'SATKER']);
    $bidang = UnitOrganisasi::factory()->create(['jenis' => 'BIDANG', 'parent_id' => $satker->id]);
    $unit = UnitOrganisasi::factory()->create(['jenis' => 'SUBBIDANG', 'parent_id' => $bidang->id]);
    $actors = [];
    foreach (['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER'] as $role) {
        $actor = User::factory()->create();
        grantRole($actor, $role, match ($role) {
            'SUPER_ADMIN' => null,'ADMIN_SATKER','KASATKER' => $satker->id,'KABID' => $bidang->id,default => $unit->id
        });
        $actors[$role] = $actor;
    }
    $document = Dokumen::factory()->create(['unit_pemilik_id' => $unit->id]);
    $proof = DokumenVersi::factory()->create(['dokumen_id' => $document->id]);
    $proof->update(['status' => 'FINAL']);
    $sop = Sop::factory()->create(['unit_pemilik_id' => $unit->id]);
    $sopVersion = SopVersi::factory()->create(['sop_id' => $sop->id, 'dokumen_versi_id' => $proof->id, 'dibuat_oleh' => $actors['ADMIN_SATKER']->id]);
    $soa = Soa::factory()->create(['unit_id' => $unit->id]);
    $soaVersion = SoaVersi::factory()->create(['soa_id' => $soa->id, 'dibuat_oleh' => $actors['STAFF']->id]);
    $control = Kontrol::factory()->create(['standar_id' => $soa->standar_id]);
    $detail = SoaDetail::factory()->create(['soa_versi_id' => $soaVersion->id, 'kontrol_id' => $control->id]);
    $internal = KontrolInternal::factory()->create(['unit_pemilik_id' => $unit->id]);

    return compact('unit', 'satker', 'bidang', 'actors', 'proof', 'sop', 'sopVersion', 'soa', 'soaVersion', 'control', 'detail', 'internal');
}
test('compliance draft reads and updates enforce all roles', function (string $role, string $resource, string $key, array $payload): void {
    $f = kepatuhanFixture();
    $url = '/api/'.$resource.'/'.$f[$key]->id;
    $this->actingAs($f['actors'][$role])->getJson($url)->assertOk();
    $response = $this->patchJson($url, $payload);
    $staffRestricted = $role === 'STAFF' && in_array($resource, ['kontrol-internal', 'sop', 'sop-versi'], true);
    if (in_array($role, ['STAFF', 'ADMIN_SATKER'], true) && ! $staffRestricted) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER'])->with([['kontrol-internal', 'internal', ['nama' => 'Kontrol baru']], ['sop', 'sop', ['judul' => 'Prosedur baru']], ['soa', 'soa', ['ruang_lingkup' => 'Lingkup baru']], ['sop-versi', 'sopVersion', ['tanggal_berlaku' => '2026-10-13']], ['soa-detail', 'detail', ['alasan' => 'Alasan baru']]]);
test('SOP and SOA journey freezes descendants and keeps review history', function (string $resource, string $key): void {
    $f = kepatuhanFixture();
    $id = $f[$key]->id;
    $url = '/api/'.$resource.'/'.$id;
    $this->actingAs($f['actors']['STAFF'])->postJson($url.'/ajukan')->assertForbidden();
    $submittedStatus = $resource === 'sop-versi' ? 'MENUNGGU_SUPER_ADMIN' : 'MENUNGGU_KASUB';
    if ($resource === 'sop-versi') {
        foreach (['STAFF', 'KASUB', 'KABID', 'KASATKER'] as $role) {
            $this->actingAs($f['actors'][$role])->postJson($url.'/ajukan')->assertForbidden();
        }
    }
    $this->actingAs($f['actors']['ADMIN_SATKER'])->postJson($url.'/ajukan')->assertOk()->assertJsonPath('data.status', $submittedStatus);
    $this->patchJson($url, [])->assertConflict();
    if ($resource === 'sop-versi') {
        foreach (['ADMIN_SATKER', 'KASUB', 'KABID', 'KASATKER'] as $role) {
            $this->actingAs($f['actors'][$role])->postJson($url.'/review', ['tahap' => 'SUPER_ADMIN', 'keputusan' => 'SETUJU'])->assertForbidden();
        }
        $this->actingAs($f['actors']['SUPER_ADMIN'])->postJson($url.'/review', ['tahap' => 'SUPER_ADMIN', 'keputusan' => 'SETUJU'])->assertOk()->assertJsonPath('data.status', 'DISAHKAN');
        $this->assertDatabaseHas('SOP_REVIEW', ['sop_versi_id' => $id, 'reviewer_id' => $f['actors']['SUPER_ADMIN']->id, 'tahap' => 'SUPER_ADMIN', 'keputusan' => 'SETUJU']);
        $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'SOP_VERSI', 'entitas_id' => $id, 'aksi' => 'APPROVE']);
    } else {
        foreach (['KASUB' => 'MENUNGGU_KABID', 'KABID' => 'MENUNGGU_KASATKER', 'KASATKER' => 'DISAHKAN'] as $role => $state) {
            $this->actingAs($f['actors'][$role])->postJson($url.'/review', ['tahap' => $role, 'keputusan' => 'SETUJU'])->assertOk()->assertJsonPath('data.status', $state);
        }
    }
    $this->actingAs($f['actors']['ADMIN_SATKER'])->deleteJson($url)->assertConflict();
    if ($resource === 'soa-versi') {
        $this->patchJson('/api/soa-detail/'.$f['detail']->id, ['alasan' => 'Ubah beku'])->assertConflict();
        $this->postJson('/api/soa-detail/'.$f['detail']->id.'/benchmark', ['regulasi_id' => Regulasi::factory()->create()->id, 'rujukan' => 'Pasal 2'])->assertConflict();
    }
    $this->getJson('/api/'.str_replace('versi', 'review', $resource))->assertOk()->assertJsonCount($resource === 'sop-versi' ? 1 : 3, 'data');
})->with([['sop-versi', 'sopVersion'], ['soa-versi', 'soaVersion']]);
test('SOP reviewers are limited to super admin while SOA keeps its existing hierarchy', function (string $role, string $stage, string $resource, string $key): void {
    $f = kepatuhanFixture();
    $sop = $resource === 'sop-versi';
    $actualStage = $sop ? 'SUPER_ADMIN' : $stage;
    DB::table($f[$key]->getTable())->where('id', $f[$key]->id)->update(['status' => $sop ? 'MENUNGGU_SUPER_ADMIN' : 'MENUNGGU_'.$stage]);
    $response = $this->actingAs($f['actors'][$role])->postJson('/api/'.$resource.'/'.$f[$key]->id.'/review', ['tahap' => $actualStage, 'keputusan' => 'TOLAK', 'catatan' => 'Perbaiki']);
    if ($sop) {
        if ($role === 'SUPER_ADMIN') {
            $response->assertOk()->assertJsonPath('data.status', 'DITOLAK');
            $this->assertDatabaseHas('SOP_REVIEW', ['sop_versi_id' => $f[$key]->id, 'tahap' => 'SUPER_ADMIN', 'keputusan' => 'TOLAK']);
            $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'SOP_VERSI', 'entitas_id' => $f[$key]->id, 'aksi' => 'REJECT']);
        } else {
            $response->assertForbidden();
        }

        return;
    }
    if ($role === $stage) {
        $response->assertOk()->assertJsonPath('data.status', 'DITOLAK');
    } else {
        $response->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER'])->with(['KASUB', 'KABID', 'KASATKER'])->with([['sop-versi', 'sopVersion'], ['soa-versi', 'soaVersion']]);
test('nested drafts allocate versions and validate document and standard ownership', function (): void {
    $f = kepatuhanFixture();
    $this->actingAs($f['actors']['ADMIN_SATKER']);
    $this->postJson('/api/sop/'.$f['sop']->id.'/versi', ['dokumen_versi_id' => $f['proof']->id, 'tanggal_berlaku' => '2026-10-13'])->assertCreated()->assertJsonPath('data.nomor_versi', '2');
    $this->postJson('/api/soa/'.$f['soa']->id.'/versi')->assertCreated()->assertJsonPath('data.nomor_versi', '2');
    $draft = DokumenVersi::factory()->create(['dokumen_id' => $f['proof']->dokumen_id, 'nomor_versi' => 2]);
    $this->postJson('/api/sop/'.$f['sop']->id.'/versi', ['dokumen_versi_id' => $draft->id, 'tanggal_berlaku' => '2026-10-13'])->assertConflict();
    $this->postJson('/api/soa-versi/'.$f['soaVersion']->id.'/detail', ['kontrol_id' => Kontrol::factory()->create()->id, 'berlaku' => true, 'alasan' => 'Alasan', 'status_penerapan' => 'BELUM_DITERAPKAN'])->assertNotFound();
    $this->postJson('/api/soa-detail/'.$f['detail']->id.'/benchmark', ['regulasi_id' => Regulasi::factory()->create()->id, 'rujukan' => 'Pasal 3'])->assertCreated();
    $this->postJson('/api/soa-detail/'.$f['detail']->id.'/bukti', ['dokumen_versi_id' => $f['proof']->id, 'keterangan' => 'Bukti'])->assertCreated();
    $this->getJson('/api/soa-detail/'.$f['detail']->id.'/bukti')->assertOk()->assertJsonCount(1, 'data');
});
test('compliance scope hides direct nested and linked foreign records', function (): void {
    $f = kepatuhanFixture();
    $actor = User::factory()->create();
    grantRole($actor, 'ADMIN_SATKER', UnitOrganisasi::factory()->create()->id);
    $this->actingAs($actor);
    foreach (['sop' => 'sop', 'sop-versi' => 'sopVersion', 'soa' => 'soa', 'soa-versi' => 'soaVersion', 'soa-detail' => 'detail', 'kontrol-internal' => 'internal'] as $resource => $key) {
        $this->getJson('/api/'.$resource.'/'.$f[$key]->id)->assertNotFound();
        $this->patchJson('/api/'.$resource.'/'.$f[$key]->id, [])->assertNotFound();
    }
    $this->postJson('/api/soa-detail/'.$f['detail']->id.'/bukti', ['dokumen_versi_id' => $f['proof']->id, 'keterangan' => 'Tidak boleh'])->assertNotFound();
    $this->getJson('/api/sop')->assertOk()->assertJsonCount(0, 'data');
});
test('SOP linking requires approved versions and matching internal control pivots', function (): void {
    $f = kepatuhanFixture();
    $assessment = AsesmenRisiko::factory()->create(['unit_id' => $f['unit']->id, 'dibuat_oleh' => $f['actors']['STAFF']->id]);
    $risk = Risiko::factory()->create(['asesmen_id' => $assessment->id]);
    $this->actingAs($f['actors']['ADMIN_SATKER']);
    $sopPayload = ['sop_versi_id' => $f['sopVersion']->id];
    $this->postJson('/api/kontrol-internal/'.$f['internal']->id.'/sop', $sopPayload)->assertConflict();
    DB::table('SOP_VERSI')->where('id', $f['sopVersion']->id)->update(['status' => 'DISAHKAN']);
    $this->postJson('/api/kontrol-internal/'.$f['internal']->id.'/standar', ['kontrol_id' => $f['control']->id, 'keterangan' => 'Acuan'])->assertCreated();
    $this->postJson('/api/kontrol-internal/'.$f['internal']->id.'/sop', $sopPayload)->assertCreated();
    $this->postJson('/api/risiko/'.$risk->id.'/sop', [...$sopPayload, 'kontrol_internal_id' => $f['internal']->id])->assertConflict();
    $this->postJson('/api/risiko/'.$risk->id.'/kontrol-internal', ['kontrol_internal_id' => $f['internal']->id, 'status_penerapan' => 'DITERAPKAN', 'kondisi_penerapan' => 'Sesuai', 'diperiksa_pada' => now()->toIso8601String()])->assertCreated();
    $this->postJson('/api/risiko/'.$risk->id.'/sop', [...$sopPayload, 'kontrol_internal_id' => $f['internal']->id])->assertCreated();
    DB::table('ASESMEN_RISIKO')->where('id', $assessment->id)->update(['status' => 'PENANGANAN', 'dibekukan_awal_pada' => now()]);
    $this->deleteJson('/api/risiko/'.$risk->id.'/sop', [...$sopPayload, 'kontrol_internal_id' => $f['internal']->id])->assertConflict();
});
test('staff cannot change SOP and internal-control links but may still write SOA evidence', function (): void {
    $f = kepatuhanFixture();
    $this->actingAs($f['actors']['STAFF']);
    $this->postJson('/api/sop-versi/'.$f['sopVersion']->id.'/kontrol', ['kontrol_id' => $f['control']->id])->assertForbidden();
    $this->postJson('/api/kontrol-internal/'.$f['internal']->id.'/sop', ['sop_versi_id' => $f['sopVersion']->id])->assertForbidden();
    $this->postJson('/api/kontrol-internal/'.$f['internal']->id.'/standar', ['kontrol_id' => $f['control']->id, 'keterangan' => 'Acuan'])->assertForbidden();
    $this->postJson('/api/soa-detail/'.$f['detail']->id.'/bukti', ['dokumen_versi_id' => $f['proof']->id, 'keterangan' => 'Bukti'])->assertCreated();
});
test('compliance requires rejection notes and denies self review with atomic audit failure', function (): void {
    $f = kepatuhanFixture();
    DB::table('SOP_VERSI')->where('id', $f['sopVersion']->id)->update(['status' => 'MENUNGGU_SUPER_ADMIN', 'dibuat_oleh' => $f['actors']['SUPER_ADMIN']->id]);
    $url = '/api/sop-versi/'.$f['sopVersion']->id.'/review';
    $this->actingAs($f['actors']['ADMIN_SATKER'])->postJson($url, ['tahap' => 'SUPER_ADMIN', 'keputusan' => 'SETUJU'])->assertForbidden();
    $this->actingAs($f['actors']['SUPER_ADMIN'])->postJson($url, ['tahap' => 'SUPER_ADMIN', 'keputusan' => 'SETUJU'])->assertConflict()->assertJsonPath('code', 'pemisahan_tugas');
    DB::table('SOP_VERSI')->where('id', $f['sopVersion']->id)->update(['dibuat_oleh' => $f['actors']['STAFF']->id]);
    $this->actingAs($f['actors']['SUPER_ADMIN'])->postJson($url, ['tahap' => 'SUPER_ADMIN', 'keputusan' => 'TOLAK'])->assertUnprocessable()->assertJsonValidationErrors(['catatan']);
    $realAudit = new AuditService;
    $this->mock(AuditService::class)->shouldReceive('record')->andReturnUsing(function (string $table, int $id, string $action, ?array $before = null, ?array $after = null, ?int $actor = null) use ($realAudit): void {
        if ($action === 'APPROVE') {
            throw new RuntimeException('Audit gagal');
        }
        $realAudit->record($table, $id, $action, $before, $after, $actor);
    });
    $this->actingAs($f['actors']['SUPER_ADMIN'])->postJson($url, ['tahap' => 'SUPER_ADMIN', 'keputusan' => 'SETUJU'])->assertStatus(500);
    $this->assertDatabaseCount('SOP_REVIEW', 0);
    $this->assertDatabaseMissing('LOG_AUDIT', ['entitas' => 'SOP_VERSI', 'entitas_id' => $f['sopVersion']->id, 'aksi' => 'APPROVE']);
    expect($f['sopVersion']->fresh()->status)->toBe('MENUNGGU_SUPER_ADMIN');
});

test('draft creation and deletion follow role matrix', function (string $role): void {
    $f = kepatuhanFixture();
    $this->actingAs($f['actors'][$role]);
    foreach (['kontrol-internal' => ['unit_pemilik_id' => $f['unit']->id, 'kode' => 'Baru', 'nama' => 'Kontrol', 'jenis' => 'TEKNIS', 'deskripsi' => 'Deskripsi'], 'sop' => ['unit_pemilik_id' => $f['unit']->id, 'kode' => 'SOP-BARU', 'judul' => 'Prosedur'], 'soa' => ['unit_id' => $f['unit']->id, 'periode_id' => $f['soa']->periode_id, 'standar_id' => $f['soa']->standar_id, 'ruang_lingkup' => 'Lingkup']] as $resource => $payload) {
        $response = $this->postJson('/api/'.$resource, $payload);
        $staffRestricted = $role === 'STAFF' && in_array($resource, ['kontrol-internal', 'sop'], true);
        if (in_array($role, ['STAFF', 'ADMIN_SATKER'], true) && ! $staffRestricted) {
            $response->assertCreated();
            $delete = $this->deleteJson('/api/'.$resource.'/'.$response->json('data.id'));
            if ($role === 'ADMIN_SATKER') {
                $delete->assertOk();
            } else {
                $delete->assertForbidden();
            }
        } else {
            $response->assertForbidden();
        }
    }
    $sopResponse = $this->postJson('/api/sop/'.$f['sop']->id.'/versi', ['dokumen_versi_id' => $f['proof']->id, 'tanggal_berlaku' => '2026-10-13']);
    if ($role === 'ADMIN_SATKER') {
        $sopResponse->assertCreated();
    } else {
        $sopResponse->assertForbidden();
    }
    $soaResponse = $this->postJson('/api/soa/'.$f['soa']->id.'/versi');
    if (in_array($role, ['STAFF', 'ADMIN_SATKER'], true)) {
        $soaResponse->assertCreated();
    } else {
        $soaResponse->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER']);
test('parent standard cannot change after versions and invalid sorts fail validation', function (): void {
    $f = kepatuhanFixture();
    $this->actingAs($f['actors']['ADMIN_SATKER'])->patchJson('/api/soa/'.$f['soa']->id, ['standar_id' => StandarKontrol::factory()->create()->id])->assertConflict();
    $this->getJson('/api/soa?sort=unknown')->assertUnprocessable();
    $this->getJson('/api/soa?sort=-id&filter[unit_id]='.$f['unit']->id)->assertOk()->assertJsonCount(1, 'data');
});

test('linked SOP references cannot be orphaned and pivot filters use an allowlist', function (): void {
    $f = kepatuhanFixture();
    DB::table('SOP_VERSI')->where('id', $f['sopVersion']->id)->update(['status' => 'DISAHKAN']);
    $assessment = AsesmenRisiko::factory()->create(['unit_id' => $f['unit']->id, 'dibuat_oleh' => $f['actors']['STAFF']->id]);
    $risk = Risiko::factory()->create(['asesmen_id' => $assessment->id]);
    $this->actingAs($f['actors']['ADMIN_SATKER']);
    $this->postJson('/api/kontrol-internal/'.$f['internal']->id.'/sop', ['sop_versi_id' => $f['sopVersion']->id])->assertCreated();
    $this->postJson('/api/risiko/'.$risk->id.'/kontrol-internal', ['kontrol_internal_id' => $f['internal']->id, 'status_penerapan' => 'DITERAPKAN', 'kondisi_penerapan' => 'Sesuai', 'diperiksa_pada' => now()->toIso8601String()])->assertCreated();
    $this->postJson('/api/risiko/'.$risk->id.'/sop', ['kontrol_internal_id' => $f['internal']->id, 'sop_versi_id' => $f['sopVersion']->id])->assertCreated();
    $this->deleteJson('/api/kontrol-internal/'.$f['internal']->id.'/sop', ['sop_versi_id' => $f['sopVersion']->id])->assertConflict();
    $this->getJson('/api/kontrol-internal/'.$f['internal']->id.'/sop?sort=unknown')->assertUnprocessable();
    $this->getJson('/api/kontrol-internal/'.$f['internal']->id.'/sop?filter[sop_versi_id]='.$f['sopVersion']->id)->assertOk()->assertJsonCount(1, 'data');
});
