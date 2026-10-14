<?php

use App\Exceptions\BusinessRuleException;
use App\Models\AreaDampak;
use App\Models\AsesmenAset;
use App\Models\AsesmenRisiko;
use App\Models\Aset;
use App\Models\MatriksRisiko;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\PenilaianRisiko;
use App\Models\PeriodePenilaian;
use App\Models\Risiko;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UsulanKeputusan;
use App\Models\VersiMetodeRisiko;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);
function asesmenFixture(string $role = 'STAFF'): array
{
    $unit = UnitOrganisasi::factory()->create();
    $user = User::factory()->create();
    grantRole($user, $role, $role === 'SUPER_ADMIN' ? null : $unit->id);
    $employee = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create(['pegawai_id' => $employee->id, 'unit_id' => $unit->id, 'mulai' => today()->subDay()]);
    $area = AreaDampak::factory()->create();
    $method = VersiMetodeRisiko::factory()->frozen()->create();
    $period = PeriodePenilaian::factory()->create(['tanggal_mulai' => today()->subMonth(), 'tanggal_selesai' => today()->addMonth()]);
    $assessment = AsesmenRisiko::factory()->create(['unit_id' => $unit->id, 'dibuat_oleh' => $user->id, 'periode_id' => $period->id, 'versi_metode_id' => $method->id]);
    $risk = Risiko::factory()->create(['asesmen_id' => $assessment->id, 'dibuat_oleh' => $user->id, 'pemilik_risiko_id' => $employee->id, 'area_dampak_id' => $area->id]);

    return compact('unit', 'user', 'employee', 'area', 'method', 'period', 'assessment', 'risk');
}
test('assessment read and write permissions follow all roles', function (string $role): void {
    $f = asesmenFixture($role);
    $this->actingAs($f['user']);
    $id = $f['assessment']->id;
    foreach (['/api/asesmen', '/api/asesmen/'.$id, '/api/asesmen/'.$id.'/risiko', '/api/asesmen/'.$id.'/aset', '/api/risiko/'.$f['risk']->id, '/api/risiko/'.$f['risk']->id.'/penilaian', '/api/risiko/'.$f['risk']->id.'/usulan-keputusan'] as $url) {
        $this->getJson($url)->assertOk();
    }
    $can = in_array($role, ['STAFF', 'ADMIN_SATKER'], true);
    $response = $this->patchJson('/api/asesmen/'.$id, ['tujuan' => 'Tujuan baru']);
    if ($can) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
    $payload = $f['risk']->only(['ancaman_id', 'kerentanan_id', 'area_dampak_id', 'pemilik_risiko_id', 'kode', 'skenario', 'uraian_kerentanan', 'uraian_dampak', 'arah_dampak']);
    $payload['kode'] = 'RISIKO-BARU';
    $response = $this->postJson('/api/asesmen/'.$id.'/risiko', $payload);
    if ($can) {
        $response->assertCreated();
    } else {
        $response->assertForbidden();
    }
    $rating = ['matriks_id' => MatriksRisiko::where('versi_metode_id', $f['method']->id)->firstOrFail()->id, 'tahap' => 'SAAT_INI', 'alasan_kemungkinan' => 'Alasan', 'alasan_dampak' => 'Alasan', 'kesimpulan' => 'Kesimpulan'];
    $response = $this->postJson('/api/risiko/'.$f['risk']->id.'/penilaian', $rating);
    if ($can) {
        $response->assertCreated();
    } else {
        $response->assertForbidden();
    }
    $response = $this->postJson('/api/risiko/'.$f['risk']->id.'/usulan-keputusan', ['keputusan' => 'MITIGASI', 'alasan' => 'Alasan']);
    if ($can) {
        $response->assertCreated();
    } else {
        $response->assertForbidden();
    }
    $asset = Aset::factory()->create(['unit_pemilik_id' => $f['unit']->id]);
    $response = $this->postJson('/api/asesmen/'.$id.'/aset', ['aset_id' => $asset->id, 'nilai_kekritisan' => 3, 'dasar_kekritisan' => 'Dasar']);
    if ($can) {
        $response->assertCreated();
    } else {
        $response->assertForbidden();
    }
})->with(['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF']);
test('assessment creation requires running period frozen method and a unique active series', function (): void {
    $f = asesmenFixture();
    $otherUnit = UnitOrganisasi::factory()->create();
    grantRole($f['user'], 'STAFF', $otherUnit->id);
    $payload = ['unit_id' => $otherUnit->id, 'periode_id' => $f['period']->id, 'versi_metode_id' => $f['method']->id, 'tujuan' => 'Tujuan', 'ruang_lingkup' => 'Lingkup'];
    $draft = VersiMetodeRisiko::factory()->create();
    $this->actingAs($f['user'])->postJson('/api/asesmen', [...$payload, 'versi_metode_id' => $draft->id])->assertConflict()->assertJsonPath('code', 'versi_belum_beku');
    $expired = PeriodePenilaian::factory()->create(['tanggal_mulai' => today()->subYears(2), 'tanggal_selesai' => today()->subYear()]);
    $this->postJson('/api/asesmen', [...$payload, 'periode_id' => $expired->id])->assertConflict()->assertJsonPath('code', 'periode_tidak_berjalan');
    $id = $this->postJson('/api/asesmen', $payload)->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.nomor_versi', 1)->json('data.id');
    $this->postJson('/api/asesmen', $payload)->assertConflict()->assertJsonPath('code', 'seri_aktif_sudah_ada');
    $this->assertDatabaseHas('LOG_AUDIT', ['entitas' => 'ASESMEN_RISIKO', 'entitas_id' => $id, 'aksi' => 'CREATE', 'user_id' => $f['user']->id]);
});
test('snapshot stays unchanged after source asset and catalog updates', function (): void {
    $f = asesmenFixture();
    $asset = Aset::factory()->create(['unit_pemilik_id' => $f['unit']->id, 'nama' => 'Nama lama', 'lokasi' => 'Lab lama']);
    $id = $this->actingAs($f['user'])->postJson('/api/asesmen/'.$f['assessment']->id.'/aset', ['aset_id' => $asset->id, 'nilai_kekritisan' => 2, 'dasar_kekritisan' => 'Dasar'])->assertCreated()->assertJsonPath('data.nama_snapshot', 'Nama lama')->json('data.id');
    $asset->update(['nama' => 'Nama baru', 'lokasi' => 'Lab baru']);
    $snapshot = AsesmenAset::findOrFail($id);
    expect($snapshot->nama_snapshot)->toBe('Nama lama');
    expect($snapshot->lokasi_snapshot)->toBe('Lab lama');
    $this->patchJson('/api/asesmen-aset/'.$id, ['nama_snapshot' => 'Palsu'])->assertUnprocessable();
    expect(fn () => $snapshot->update(['nama_snapshot' => 'Palsu']))->toThrow(BusinessRuleException::class);
    $this->postJson('/api/asesmen/'.$f['assessment']->id.'/aset', ['aset_id' => $asset->id, 'nilai_kekritisan' => 2, 'dasar_kekritisan' => 'Dasar'])->assertConflict();
});
test('cross assessment links and cross version ratings are rejected atomically', function (): void {
    $f = asesmenFixture();
    $other = AsesmenRisiko::factory()->create(['unit_id' => $f['unit']->id, 'dibuat_oleh' => $f['user']->id]);
    $snapshot = AsesmenAset::factory()->create(['asesmen_id' => $other->id]);
    $this->actingAs($f['user'])->patchJson('/api/risiko/'.$f['risk']->id, ['skenario' => 'Tidak tersimpan', 'asesmen_aset_ids' => [$snapshot->id]])->assertConflict()->assertJsonPath('code', 'aset_beda_asesmen');
    expect($f['risk']->fresh()->skenario)->toBe('Skenario');
    $foreignCell = MatriksRisiko::where('versi_metode_id', $other->versi_metode_id)->firstOrFail();
    $this->postJson('/api/risiko/'.$f['risk']->id.'/penilaian', ['matriks_id' => $foreignCell->id, 'tahap' => 'SAAT_INI', 'alasan_kemungkinan' => 'A', 'alasan_dampak' => 'B', 'kesimpulan' => 'C'])->assertConflict()->assertJsonPath('code', 'matriks_beda_versi');
    $this->assertDatabaseCount('PENILAIAN_RISIKO', 0);
});
test('foreign unit and unrelated staff cannot mutate an assessment', function (): void {
    $f = asesmenFixture();
    $stranger = User::factory()->create();
    grantRole($stranger, 'STAFF', UnitOrganisasi::factory()->create()->id);
    $this->actingAs($stranger)->getJson('/api/asesmen/'.$f['assessment']->id)->assertNotFound();
    $this->patchJson('/api/risiko/'.$f['risk']->id, ['skenario' => 'X'])->assertNotFound();
    grantRole($stranger, 'STAFF', $f['unit']->id);
    $this->patchJson('/api/asesmen/'.$f['assessment']->id, ['tujuan' => 'X'])->assertForbidden();
});
test('initial assessments cannot bypass status or early freeze protection', function (): void {
    $f = asesmenFixture();
    $this->actingAs($f['user'])->patchJson('/api/asesmen/'.$f['assessment']->id, ['status' => 'DISAHKAN'])->assertUnprocessable();
    DB::table('ASESMEN_RISIKO')->where('id', $f['assessment']->id)->update(['status' => 'PENANGANAN', 'dibekukan_awal_pada' => now()]);
    $this->patchJson('/api/asesmen/'.$f['assessment']->id, ['tujuan' => 'X'])->assertConflict();
    $this->patchJson('/api/risiko/'.$f['risk']->id, ['skenario' => 'X'])->assertConflict();
    $this->deleteJson('/api/risiko/'.$f['risk']->id)->assertConflict();
    expect(fn () => $f['risk']->update(['skenario' => 'X']))->toThrow(BusinessRuleException::class);
});
test('ratings update the same initial stage and proposals follow current rating', function (): void {
    $f = asesmenFixture();
    $cell = MatriksRisiko::where('versi_metode_id', $f['method']->id)->firstOrFail();
    $this->actingAs($f['user']);
    $this->postJson('/api/risiko/'.$f['risk']->id.'/usulan-keputusan', ['keputusan' => 'TERIMA', 'alasan' => 'A'])->assertConflict();
    $data = ['matriks_id' => $cell->id, 'tahap' => 'SAAT_INI', 'alasan_kemungkinan' => 'A', 'alasan_dampak' => 'B', 'kesimpulan' => 'C'];
    $this->postJson('/api/risiko/'.$f['risk']->id.'/penilaian', $data)->assertCreated();
    $this->postJson('/api/risiko/'.$f['risk']->id.'/penilaian', [...$data, 'kesimpulan' => 'Baru'])->assertOk();
    $this->postJson('/api/risiko/'.$f['risk']->id.'/usulan-keputusan', ['keputusan' => 'TERIMA', 'alasan' => 'A'])->assertCreated();
    $this->postJson('/api/risiko/'.$f['risk']->id.'/usulan-keputusan', ['keputusan' => 'MITIGASI', 'alasan' => 'B'])->assertOk();
    expect(PenilaianRisiko::count())->toBe(1);
    expect(UsulanKeputusan::count())->toBe(1);
});
test('draft risk and unused snapshot can be deleted but linked snapshot cannot', function (): void {
    $f = asesmenFixture();
    $snapshot = AsesmenAset::factory()->create(['asesmen_id' => $f['assessment']->id]);
    $this->actingAs($f['user'])->patchJson('/api/risiko/'.$f['risk']->id, ['asesmen_aset_ids' => [$snapshot->id]])->assertOk();
    $this->deleteJson('/api/asesmen-aset/'.$snapshot->id)->assertConflict();
    $this->patchJson('/api/risiko/'.$f['risk']->id, ['asesmen_aset_ids' => []])->assertOk();
    $this->deleteJson('/api/asesmen-aset/'.$snapshot->id)->assertOk();
    $this->deleteJson('/api/risiko/'.$f['risk']->id)->assertOk();
});
test('assessment routes require authentication and validate list columns', function (): void {
    $this->getJson('/api/asesmen')->assertUnauthorized();
    $this->postJson('/api/asesmen')->assertUnauthorized();
    $f = asesmenFixture();
    $this->actingAs($f['user'])->getJson('/api/asesmen?sort=id%20desc')->assertUnprocessable();
    $this->getJson('/api/asesmen?per_page=101')->assertUnprocessable();
});
