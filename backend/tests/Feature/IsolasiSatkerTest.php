<?php

use App\Models\Aset;
use App\Models\Dokumen;
use App\Models\DokumenVersi;
use App\Models\FileLampiran;
use App\Models\KategoriAset;
use App\Models\KontrolInternal;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\PenugasanAset;
use App\Models\Role;
use App\Models\Sop;
use App\Models\SopVersi;
use App\Models\SumberAset;
use App\Models\UnitOrganisasi;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('lampiran');
    config(['queue.default' => 'sync']);
});

function isolasiSession(User $user): mixed
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
}

function isolasiActor(string $satkerKode): array
{
    $satker = UnitOrganisasi::factory()->create([
        'kode' => $satkerKode,
        'nama' => 'Satker '.$satkerKode,
        'jenis' => 'SATKER',
        'parent_id' => null,
    ]);
    $pegawai = Pegawai::factory()->create();
    PenempatanPegawai::factory()->create([
        'pegawai_id' => $pegawai->id,
        'unit_id' => $satker->id,
        'mulai' => today()->subMonth(),
        'selesai' => null,
    ]);
    $user = User::factory()->create(['pegawai_id' => $pegawai->id]);
    grantRole($user, 'ADMIN_SATKER', $satker->id);

    return [$user, $satker];
}

test('dokumen: dua satker boleh memakai kode dokumen yang sama tetapi satu satker tidak boleh kode ganda', function (): void {
    [$adminA, $satkerA] = isolasiActor('SATKER-A');
    [$adminB, $satkerB] = isolasiActor('SATKER-B');

    // 1. Satker A membuat dokumen dengan kode DOK-SAMA-01 -> berhasil
    isolasiSession($adminA)->postJson('/api/dokumen', [
        'unit_pemilik_id' => $satkerA->id,
        'kode' => 'DOK-SAMA-01',
        'judul' => 'Dokumen Satker A',
        'jenis' => 'BUKTI',
    ])->assertCreated();

    // 2. Satker B membuat dokumen dengan kode DOK-SAMA-01 yang sama -> berhasil (karena unit berbeda)
    isolasiSession($adminB)->postJson('/api/dokumen', [
        'unit_pemilik_id' => $satkerB->id,
        'kode' => 'DOK-SAMA-01',
        'judul' => 'Dokumen Satker B',
        'jenis' => 'BUKTI',
    ])->assertCreated();

    // 3. Satker A membuat dokumen lagi dengan kode DOK-SAMA-01 di unitnya -> ditolak (422 unique)
    isolasiSession($adminA)->postJson('/api/dokumen', [
        'unit_pemilik_id' => $satkerA->id,
        'kode' => 'DOK-SAMA-01',
        'judul' => 'Dokumen Duplikat di Satker A',
        'jenis' => 'LAPORAN',
    ])->assertUnprocessable()->assertJsonValidationErrors(['kode']);
});

test('isolasi lintas satker: admin satker A tidak boleh membaca mengubah atau menghapus data satker B', function (): void {
    [$adminA, $satkerA] = isolasiActor('SATKER-A');
    [$adminB, $satkerB] = isolasiActor('SATKER-B');

    // Buat data milik Satker B
    $pegawaiB = Pegawai::factory()->create();
    $penempatanB = PenempatanPegawai::factory()->create([
        'pegawai_id' => $pegawaiB->id,
        'unit_id' => $satkerB->id,
        'mulai' => today()->subMonth(),
        'selesai' => null,
    ]);
    $userB = User::factory()->create(['pegawai_id' => $pegawaiB->id]);
    $roleStaff = Role::firstOrCreate(['kode' => 'STAFF'], ['nama' => 'STAFF']);
    $scopeB = grantRole($userB, 'STAFF', $satkerB->id);

    $sumber = SumberAset::factory()->create();
    $kategori = KategoriAset::factory()->create();
    $asetB = Aset::factory()->create([
        'sumber_id' => $sumber->id,
        'unit_pemilik_id' => $satkerB->id,
        'kategori_id' => $kategori->id,
    ]);
    $penugasanB = PenugasanAset::factory()->create([
        'aset_id' => $asetB->id,
        'pegawai_id' => $pegawaiB->id,
        'jenis_tanggung_jawab' => 'PIC',
        'mulai' => today()->toDateString(),
    ]);

    $dokumenB = Dokumen::factory()->create([
        'unit_pemilik_id' => $satkerB->id,
        'dibuat_oleh' => $userB->id,
    ]);
    $versiId = DB::table('DOKUMEN_VERSI')->insertGetId([
        'dokumen_id' => $dokumenB->id,
        'dibuat_oleh' => $userB->id,
        'nomor_versi' => 1,
        'status' => 'FINAL',
        'isi_teks' => 'Isi teks dokumen final',
        'format_teks' => 'PLAIN',
        'dibuat_pada' => now(),
        'dibekukan_pada' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $versiB = DokumenVersi::findOrFail($versiId);
    $fileB = FileLampiran::factory()->create([
        'status_pemeriksaan' => 'AMAN',
    ]);
    DB::table('DOKUMEN_VERSI_FILE')->insert([
        'dokumen_versi_id' => $versiB->id,
        'file_id' => $fileB->id,
        'keterangan' => 'Lampiran B',
        'urutan' => 1,
    ]);

    $sopB = Sop::factory()->create([
        'unit_pemilik_id' => $satkerB->id,
    ]);
    $sopVersiB = SopVersi::factory()->create([
        'sop_id' => $sopB->id,
        'dokumen_versi_id' => $versiB->id,
        'dibuat_oleh' => $userB->id,
        'status' => 'DRAFT',
    ]);
    $kontrolB = KontrolInternal::factory()->create([
        'unit_pemilik_id' => $satkerB->id,
    ]);

    // Admin satker A mencoba membaca, mengubah, dan menghapus seluruh resource milik B
    $client = isolasiSession($adminA);

    // 1. ASET & PENUGASAN ASET
    $res = $client->getJson('/api/aset/'.$asetB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/aset/'.$asetB->id, ['lokasi' => 'Gudang Satker A']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    $res = $client->getJson('/api/penugasan-aset/'.$penugasanB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/penugasan-aset/'.$penugasanB->id, ['jenis_tanggung_jawab' => 'PENGGUNA']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->deleteJson('/api/penugasan-aset/'.$penugasanB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 2. DOKUMEN, DOKUMEN-VERSI, FILE-LAMPIRAN
    $res = $client->getJson('/api/dokumen/'.$dokumenB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->getJson('/api/dokumen-versi/'.$versiB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/dokumen-versi/'.$versiB->id, ['isi_teks' => 'Teks dibajak']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    $res = $client->getJson('/api/file-lampiran/'.$fileB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->get('/api/file-lampiran/'.$fileB->id.'/unduh');
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 3. SOP, SOP-VERSI, KONTROL-INTERNAL
    $res = $client->getJson('/api/sop/'.$sopB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/sop/'.$sopB->id, ['judul' => 'Judul Dibajak']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    $res = $client->getJson('/api/sop-versi/'.$sopVersiB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/sop-versi/'.$sopVersiB->id, ['status' => 'DISAHKAN']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    $res = $client->getJson('/api/kontrol-internal/'.$kontrolB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/kontrol-internal/'.$kontrolB->id, ['nama' => 'Nama Dibajak']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->deleteJson('/api/kontrol-internal/'.$kontrolB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 4. UNIT-ORGANISASI
    $res = $client->getJson('/api/unit-organisasi/'.$satkerB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/unit-organisasi/'.$satkerB->id, ['nama' => 'Satker B Bajakan']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 5. PEGAWAI & PENEMPATAN-PEGAWAI
    $res = $client->getJson('/api/pegawai/'.$pegawaiB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/pegawai/'.$pegawaiB->id, ['nama' => 'Nama Pegawai B Ubah']);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    $res = $client->getJson('/api/penempatan-pegawai/'.$penempatanB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/penempatan-pegawai/'.$penempatanB->id, ['selesai' => today()->toDateString()]);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->deleteJson('/api/penempatan-pegawai/'.$penempatanB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    // 6. USERS & USER-ROLE-SCOPE
    $res = $client->getJson('/api/users/'.$userB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->patchJson('/api/users/'.$userB->id, ['aktif' => false]);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();

    $res = $client->getJson('/api/user-role-scope/'.$scopeB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
    $res = $client->deleteJson('/api/user-role-scope/'.$scopeB->id);
    expect(in_array($res->status(), [403, 404], true))->toBeTrue();
});
