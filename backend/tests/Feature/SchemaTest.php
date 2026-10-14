<?php

use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\UnitOrganisasi;
use App\Models\User;
use Database\Seeders\SimrisSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config(['simris.admin.username' => 'admin-test', 'simris.admin.email' => 'admin@example.test', 'simris.admin.password' => 'Only-for-tests-123!', 'simris.demo_password' => 'Demo-for-tests-123!']);
});

test('all ERD tables and approved additions are present', function (): void {
    preg_match_all('/^    ([A-Z_]+) \{/m', file_get_contents(base_path('../docs/erd-v3.mmd')), $tables);
    foreach ([...$tables[1], 'SINKRON_ASET'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: $table");
    }
    expect(Schema::hasColumn('ASESMEN_RISIKO', 'dibekukan_awal_pada'))->toBeTrue();
    expect(Schema::hasColumn('REVIEW_ASESMEN', 'kembali_ke'))->toBeTrue();
});

test('an employee cannot have two open primary placements', function (): void {
    $first = PenempatanPegawai::factory()->create();

    expect(fn () => PenempatanPegawai::factory()->create(['pegawai_id' => $first->pegawai_id]))->toThrow(QueryException::class);
    $this->assertDatabaseCount('PENEMPATAN_PEGAWAI', 1);
});

test('closing a primary placement releases its unique active key', function (): void {
    $first = PenempatanPegawai::factory()->create(['selesai' => '2026-01-01']);
    $second = PenempatanPegawai::factory()->create(['pegawai_id' => $first->pegawai_id]);
    PenempatanPegawai::factory()->create(['pegawai_id' => $first->pegawai_id, 'utama' => false]);

    expect($first->refresh()->kunci_utama_aktif)->toBeNull();
    expect((int) $second->refresh()->kunci_utama_aktif)->toBe($first->pegawai_id);
    $this->assertDatabaseCount('PENEMPATAN_PEGAWAI', 3);
});

test('a placement cannot reference a missing employee', function (): void {
    $unit = UnitOrganisasi::factory()->create();

    expect(fn () => PenempatanPegawai::factory()->create(['pegawai_id' => 999999, 'unit_id' => $unit->id]))->toThrow(QueryException::class);
    $this->assertDatabaseCount('PENEMPATAN_PEGAWAI', 0);
});

test('the unit view follows primary placement ancestry and includes unplaced employees', function (): void {
    $satker = UnitOrganisasi::factory()->create(['nama' => 'Satker A']);
    $bidang = UnitOrganisasi::factory()->create(['parent_id' => $satker->id, 'jenis' => 'BIDANG', 'nama' => 'Bidang A']);
    $sub = UnitOrganisasi::factory()->create(['parent_id' => $bidang->id, 'jenis' => 'SUBBIDANG', 'nama' => 'Sub A']);
    $placement = PenempatanPegawai::factory()->create(['unit_id' => $sub->id, 'mulai' => '2000-01-01']);
    PenempatanPegawai::factory()->create(['pegawai_id' => $placement->pegawai_id, 'utama' => false]);
    $unplaced = Pegawai::factory()->create();

    $row = DB::table('v_pegawai_unit')->where('pegawai_id', $placement->pegawai_id)->first();

    expect($row)->toMatchArray(['satker' => 'Satker A', 'bidang' => 'Bidang A', 'subbidang' => 'Sub A', 'bagian' => null, 'subbagian' => null]);
    expect(DB::table('v_pegawai_unit')->where('pegawai_id', $unplaced->id)->value('satker'))->toBeNull();
    $this->assertDatabaseCount('v_pegawai_unit', 2);
});

test('the unit view handles bagian and subbagian and ignores future placements', function (): void {
    $satker = UnitOrganisasi::factory()->create();
    $bagian = UnitOrganisasi::factory()->create(['parent_id' => $satker->id, 'jenis' => 'BAGIAN']);
    $sub = UnitOrganisasi::factory()->create(['parent_id' => $bagian->id, 'jenis' => 'SUBBAGIAN']);
    $placement = PenempatanPegawai::factory()->create(['unit_id' => $sub->id, 'mulai' => '2000-01-01']);
    $future = PenempatanPegawai::factory()->create(['mulai' => '2999-01-01']);

    $row = DB::table('v_pegawai_unit')->where('pegawai_id', $placement->pegawai_id)->first();

    expect((int) $row->bagian_id)->toBe($bagian->id);
    expect((int) $row->subbagian_id)->toBe($sub->id);
    expect(DB::table('v_pegawai_unit')->where('pegawai_id', $future->pegawai_id)->value('satker'))->toBeNull();
});

test('base seeding is repeatable without exposing or resetting the initial password', function (): void {
    $this->seed(SimrisSeeder::class);
    $user = User::where('username', 'admin-test')->firstOrFail();
    expect(Hash::check('Only-for-tests-123!', $user->password_hash))->toBeTrue();
    expect($user->wajib_ganti_password)->toBeTrue();
    expect($user->toArray())->not->toHaveKey('password_hash');
    $user->update(['password_hash' => 'Changed-test-password', 'wajib_ganti_password' => false]);

    $this->seed(SimrisSeeder::class);

    $this->assertDatabaseCount('ROLES', 6);
    $this->assertDatabaseCount('USERS', 1);
    $this->assertDatabaseCount('USER_ROLE_SCOPE', 1);
    $this->assertDatabaseCount('UNIT_ORGANISASI', 0);
    $this->assertDatabaseCount('ASET', 0);
    $this->assertDatabaseHas('USER_ROLE_SCOPE', ['user_id' => $user->id, 'unit_id' => null]);
    expect(Hash::check('Changed-test-password', $user->refresh()->password_hash))->toBeTrue();
    expect($user->wajib_ganti_password)->toBeFalse();
});

test('missing initial credentials roll back seeding', function (): void {
    config(['simris.admin.password' => null]);

    expect(fn () => $this->seed(SimrisSeeder::class))->toThrow(InvalidArgumentException::class);
    $this->assertDatabaseCount('ROLES', 0);
    $this->assertDatabaseCount('USERS', 0);
});

test('local seeding produces a complete frozen five by five method and all roles', function (): void {
    $this->app->instance('env', 'local');
    $this->seed(SimrisSeeder::class);

    $this->seed(SimrisSeeder::class);

    $this->assertDatabaseCount('USERS', 6);
    $this->assertDatabaseCount('UNIT_ORGANISASI', 5);
    $this->assertDatabaseCount('PENEMPATAN_PEGAWAI', 5);
    $this->assertDatabaseCount('USER_ROLE_SCOPE', 6);
    $this->assertDatabaseCount('MATRIKS_RISIKO', 25);
    $this->assertDatabaseCount('KRITERIA_DAMPAK', 10);
    $this->assertDatabaseCount('ASET', 1);
    $this->assertDatabaseHas('VERSI_METODE_RISIKO', ['dibekukan' => true]);
    $this->assertDatabaseHas('MATRIKS_RISIKO', ['skor' => 25]);
    expect((int) DB::table('MATRIKS_RISIKO')->sum('skor'))->toBe(225);
    expect(DB::table('USER_ROLE_SCOPE')->distinct()->count('role_id'))->toBe(6);
});

test('duplicate business keys are rejected', function (string $table): void {
    $this->app->instance('env', 'local');
    $this->seed(SimrisSeeder::class);
    $row = (array) DB::table($table)->first();
    unset($row['id']);
    $count = DB::table($table)->count();

    expect(fn () => DB::table($table)->insert($row))->toThrow(QueryException::class);
    $this->assertDatabaseCount($table, $count);
})->with(['KRITERIA_DAMPAK', 'TINGKAT_RISIKO', 'MATRIKS_RISIKO', 'SKALA_KEMUNGKINAN', 'SKALA_DAMPAK']);

test('asset external identifiers are unique per source even when asset codes differ', function (): void {
    $this->app->instance('env', 'local');
    $this->seed(SimrisSeeder::class);
    $row = (array) DB::table('ASET')->first();
    unset($row['id']);
    $row['kode_aset'] = 'DIFFERENT-CODE';

    expect(fn () => DB::table('ASET')->insert($row))->toThrow(QueryException::class);
    $this->assertDatabaseCount('ASET', 1);
});

test('a referenced employee cannot be physically deleted', function (): void {
    $placement = PenempatanPegawai::factory()->create();

    expect(fn () => Pegawai::whereKey($placement->pegawai_id)->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('PEGAWAI', ['id' => $placement->pegawai_id]);
});

/** @return array{unit: int, user: int, dokumen: int, versi: int} */
function schemaDocument(): array
{
    $unit = UnitOrganisasi::factory()->create()->id;
    $user = User::factory()->create()->id;
    $dokumen = DB::table('DOKUMEN')->insertGetId(['unit_pemilik_id' => $unit, 'dibuat_oleh' => $user, 'kode' => 'DOC-TEST', 'judul' => 'Dokumen pengujian', 'jenis' => 'BUKTI', 'dibuat_pada' => now()]);
    $versi = DB::table('DOKUMEN_VERSI')->insertGetId(['dokumen_id' => $dokumen, 'dibuat_oleh' => $user, 'nomor_versi' => 1, 'isi_teks' => null, 'format_teks' => 'PLAIN', 'status' => 'DRAFT', 'dibuat_pada' => now()]);

    return compact('unit', 'user', 'dokumen', 'versi');
}

test('document version numbers cannot be repeated within one document', function (): void {
    $document = schemaDocument();
    $row = (array) DB::table('DOKUMEN_VERSI')->where('id', $document['versi'])->first();
    unset($row['id']);

    expect(fn () => DB::table('DOKUMEN_VERSI')->insert($row))->toThrow(QueryException::class);
    $this->assertDatabaseCount('DOKUMEN_VERSI', 1);
});

test('files referenced by document versions cannot be deleted', function (): void {
    $document = schemaDocument();
    $file = DB::table('FILE_LAMPIRAN')->insertGetId(['diunggah_oleh' => $document['user'], 'nama_asli' => 'bukti.pdf', 'storage_key' => 'private-test-file', 'ekstensi' => 'pdf', 'mime_type_terdeteksi' => 'application/pdf', 'ukuran_byte' => 100, 'checksum_sha256' => str_repeat('a', 64), 'status_pemeriksaan' => 'PENDING', 'diunggah_pada' => now()]);
    DB::table('DOKUMEN_VERSI_FILE')->insert(['dokumen_versi_id' => $document['versi'], 'file_id' => $file, 'keterangan' => 'Bukti', 'urutan' => 1]);

    expect(fn () => DB::table('FILE_LAMPIRAN')->where('id', $file)->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('FILE_LAMPIRAN', ['id' => $file]);
});

test('SOP codes may be reused across units but not within one unit', function (): void {
    $unit = UnitOrganisasi::factory()->create();
    $other = UnitOrganisasi::factory()->create();
    DB::table('SOP')->insert(['unit_pemilik_id' => $unit->id, 'kode' => 'SOP-1', 'judul' => 'Prosedur']);
    DB::table('SOP')->insert(['unit_pemilik_id' => $other->id, 'kode' => 'SOP-1', 'judul' => 'Prosedur lain']);

    expect(fn () => DB::table('SOP')->insert(['unit_pemilik_id' => $unit->id, 'kode' => 'SOP-1', 'judul' => 'Duplikat']))->toThrow(QueryException::class);
    $this->assertDatabaseCount('SOP', 2);
});

test('a document version referenced by SOP cannot be deleted', function (): void {
    $document = schemaDocument();
    $sop = DB::table('SOP')->insertGetId(['unit_pemilik_id' => $document['unit'], 'kode' => 'SOP-1', 'judul' => 'Prosedur']);
    DB::table('SOP_VERSI')->insert(['sop_id' => $sop, 'dokumen_versi_id' => $document['versi'], 'dibuat_oleh' => $document['user'], 'nomor_versi' => '1', 'tanggal_berlaku' => '2026-01-01', 'status' => 'DRAFT', 'dibuat_pada' => now()]);

    expect(fn () => DB::table('DOKUMEN_VERSI')->where('id', $document['versi'])->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('DOKUMEN_VERSI', ['id' => $document['versi']]);
});

/** @return array{asesmen: int, risiko: int, aset: int} */
function schemaAssessment(): array
{
    app()->instance('env', 'local');
    test()->seed(SimrisSeeder::class);
    $seri = DB::table('SERI_ASESMEN')->insertGetId(['kode' => 'SERI-TEST', 'nama' => 'Seri pengujian']);
    $asesmen = DB::table('ASESMEN_RISIKO')->insertGetId(['seri_asesmen_id' => $seri, 'periode_id' => DB::table('PERIODE_PENILAIAN')->value('id'), 'unit_id' => DB::table('UNIT_ORGANISASI')->value('id'), 'versi_metode_id' => DB::table('VERSI_METODE_RISIKO')->value('id'), 'dibuat_oleh' => DB::table('USERS')->value('id'), 'tujuan' => 'Pengujian', 'ruang_lingkup' => 'Unit pengujian', 'status' => 'DRAFT', 'nomor_versi' => 1, 'dibuat_pada' => now()]);
    $risiko = DB::table('RISIKO')->insertGetId(['asesmen_id' => $asesmen, 'ancaman_id' => DB::table('ANCAMAN')->value('id'), 'kerentanan_id' => DB::table('KERENTANAN')->value('id'), 'area_dampak_id' => DB::table('AREA_DAMPAK')->value('id'), 'pemilik_risiko_id' => DB::table('PEGAWAI')->value('id'), 'dibuat_oleh' => DB::table('USERS')->value('id'), 'kode' => 'R-1', 'skenario' => 'Pengujian', 'uraian_kerentanan' => 'Pengujian', 'uraian_dampak' => 'Pengujian', 'arah_dampak' => 'N', 'dibuat_pada' => now()]);
    $aset = DB::table('ASESMEN_ASET')->insertGetId(['asesmen_id' => $asesmen, 'aset_id' => DB::table('ASET')->value('id'), 'nama_snapshot' => 'Perangkat contoh', 'kategori_snapshot' => 'Belum dikategorikan', 'klasifikasi_informasi_snapshot' => '', 'jalur_unit_snapshot' => 'Satker Contoh', 'lokasi_snapshot' => '', 'nilai_kekritisan' => 1, 'dasar_kekritisan' => 'Pengujian', 'direkam_pada' => now()]);

    return compact('asesmen', 'risiko', 'aset');
}

test('assessment version risk code and asset snapshot keys cannot be duplicated', function (string $table): void {
    schemaAssessment();
    $row = (array) DB::table($table)->first();
    unset($row['id']);

    expect(fn () => DB::table($table)->insert($row))->toThrow(QueryException::class);
    $this->assertDatabaseCount($table, 1);
})->with(['ASESMEN_RISIKO', 'RISIKO', 'ASESMEN_ASET']);

test('a risk SOP reference must use a control already linked to that risk', function (): void {
    $assessment = schemaAssessment();
    $document = schemaDocument();
    $control = DB::table('KONTROL_INTERNAL')->insertGetId(['unit_pemilik_id' => $document['unit'], 'kode' => 'KI-1', 'nama' => 'Kontrol', 'jenis' => 'SOP', 'deskripsi' => 'Pengujian', 'aktif' => true]);
    $sop = DB::table('SOP')->insertGetId(['unit_pemilik_id' => $document['unit'], 'kode' => 'SOP-1', 'judul' => 'Prosedur']);
    $version = DB::table('SOP_VERSI')->insertGetId(['sop_id' => $sop, 'dokumen_versi_id' => $document['versi'], 'dibuat_oleh' => $document['user'], 'nomor_versi' => '1', 'tanggal_berlaku' => '2026-01-01', 'status' => 'DRAFT', 'dibuat_pada' => now()]);
    $link = ['risiko_id' => $assessment['risiko'], 'kontrol_internal_id' => $control, 'sop_versi_id' => $version];

    expect(fn () => DB::table('RISIKO_KONTROL_SOP')->insert($link))->toThrow(QueryException::class);
    $this->assertDatabaseCount('RISIKO_KONTROL_SOP', 0);

    DB::table('RISIKO_KONTROL_INTERNAL')->insert(['risiko_id' => $assessment['risiko'], 'kontrol_internal_id' => $control, 'status_penerapan' => 'DITERAPKAN', 'kondisi_penerapan' => 'Pengujian', 'diperiksa_pada' => now()]);
    DB::table('RISIKO_KONTROL_SOP')->insert($link);
    $this->assertDatabaseHas('RISIKO_KONTROL_SOP', $link);
});
