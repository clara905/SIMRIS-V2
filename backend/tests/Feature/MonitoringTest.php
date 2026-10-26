<?php

use App\Models\AsesmenRisiko;
use App\Models\Dokumen;
use App\Models\DokumenVersi;
use App\Models\FileLampiran;
use App\Models\Risiko;
use App\Models\Soa;
use App\Models\Sop;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);
function monitoringFixture(string $role = 'ADMIN_SATKER'): array
{
    $units = UnitOrganisasi::factory()->count(2)->create();
    $actor = User::factory()->create();
    grantRole($actor, $role, $role === 'SUPER_ADMIN' ? null : $units[0]->id);
    $rows = [];
    foreach ($units as $unit) {
        $assessment = AsesmenRisiko::factory()->create(['unit_id' => $unit->id]);
        $risk = Risiko::factory()->create(['asesmen_id' => $assessment->id]);
        $soa = Soa::factory()->create(['unit_id' => $unit->id]);
        $sop = Sop::factory()->create(['unit_pemilik_id' => $unit->id]);
        $doc = Dokumen::factory()->create(['unit_pemilik_id' => $unit->id]);
        $version = DokumenVersi::factory()->create(['dokumen_id' => $doc->id]);
        $file = FileLampiran::factory()->create();
        DB::table('DOKUMEN_VERSI_FILE')->insert(['dokumen_versi_id' => $version->id, 'file_id' => $file->id, 'urutan' => 1, 'keterangan' => 'Bukti', 'created_at' => now(), 'updated_at' => now()]);
        $rows[] = ['asesmen' => $assessment, 'soa' => $soa, 'sop' => $sop, 'dokumen' => $doc, 'file-lampiran' => $file];
    }

    return [$actor, $rows];
}
test('monitoring role matrix filters lists details and report by unit', function (string $role): void {
    [$actor,$rows] = monitoringFixture($role);
    $this->actingAs($actor);
    foreach ($rows[0] as $resource => $model) {
        $this->getJson('/api/monitoring/'.$resource)->assertOk()->assertJsonCount($role === 'SUPER_ADMIN' ? 2 : 1, 'data');
        $this->getJson('/api/monitoring/'.$resource.'/'.$model->id)->assertOk();
        $response = $this->getJson('/api/monitoring/'.$resource.'/'.$rows[1][$resource]->id);
        if ($role === 'SUPER_ADMIN') {
            $response->assertOk();
        } else {
            $response->assertNotFound();
        } $this->postJson('/api/monitoring/'.$resource)->assertMethodNotAllowed();
    }
    $this->getJson('/api/laporan/lampiran-14?asesmen_id='.$rows[0]['asesmen']->id)->assertOk()->assertJsonPath('status_template', 'BELUM_TERSEDIA')->assertJsonPath('asesmen.id', $rows[0]['asesmen']->id)->assertJsonCount(1, 'data')->assertJsonStructure(['data' => [['penilaian', 'usulan_keputusan', 'keputusan_risiko', 'rencana_penanganan', 'residual_tercapai']], 'links', 'meta']);
    $response = $this->getJson('/api/laporan/lampiran-14?asesmen_id='.$rows[1]['asesmen']->id);
    if ($role === 'SUPER_ADMIN') {
        $response->assertOk();
    } else {
        $response->assertNotFound();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER']);
test('audit is read only and restricted to super admin including export', function (string $role): void {
    $actor = User::factory()->create();
    grantRole($actor, $role, $role === 'SUPER_ADMIN' ? null : UnitOrganisasi::factory()->create()->id);
    $this->actingAs($actor);
    foreach (['/api/log-audit', '/api/log-audit/ekspor'] as $url) {
        $response = $this->getJson($url);
        if ($role === 'SUPER_ADMIN') {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
    $this->postJson('/api/log-audit', [])->assertMethodNotAllowed();
    $this->deleteJson('/api/log-audit/1')->assertNotFound();
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'STAFF', 'KASUB', 'KABID', 'KASATKER']);
test('audit filtering export redaction and CSV formula escaping preserve safe data', function (): void {
    $actor = User::factory()->create();
    grantRole($actor, 'SUPER_ADMIN');
    $this->actingAs($actor);
    DB::transaction(fn () => app(AuditService::class)->record('UJI', 123, '=2+2', null, ['nama' => '=HYPERLINK(test)', 'token' => 'rahasia', 'isi_teks' => 'jangan tampil']));
    $this->getJson('/api/log-audit?filter[entitas]=UJI&sort=-id')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissing(['token' => 'rahasia'])->assertJsonMissing(['isi_teks' => 'jangan tampil']);
    $csv = $this->get('/api/log-audit/ekspor?filter[entitas]=UJI')->assertOk()->streamedContent();
    expect($csv)->toContain("'=2+2")->toContain('UJI')->not->toContain('rahasia')->not->toContain('jangan tampil');
    $this->getJson('/api/log-audit?filter[password_hash]=x')->assertUnprocessable();
});
test('report validates required assessment and never guesses a file export', function (): void {
    [$actor,$rows] = monitoringFixture();
    $this->actingAs($actor)->getJson('/api/laporan/lampiran-14')->assertUnprocessable();
    $this->getJson('/api/laporan/lampiran-14?asesmen_id='.$rows[0]['asesmen']->id.'&format=xlsx')->assertUnprocessable();
    $this->getJson('/api/laporan/lampiran-14?asesmen_id='.$rows[0]['asesmen']->id.'&per_page=101')->assertUnprocessable();
});
