<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\PenempatanPegawai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\MembuatPengguna;
use Tests\TestCase;

class SelfRegistrationTest extends TestCase
{
    use MembuatPengguna, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    public function test_verified_staff_registration_creates_active_local_password_without_scope(): void
    {
        $unit = UnitOrganisasi::forceCreate(['parent_id'=>null,'kode'=>'TEST-SAT','nama'=>'Satker Test','jenis'=>'SATKER','aktif'=>true]);
        $pegawai = Pegawai::forceCreate(['nip'=>'12345678','nama'=>'Budi Santoso','email_kontak'=>'kontak@example.test','jenis_kepegawaian'=>'PNS','aktif'=>true]);
        PenempatanPegawai::forceCreate(['pegawai_id'=>$pegawai->id,'unit_id'=>$unit->id,'mulai'=>now()->toDateString(),'selesai'=>null,'utama'=>true]);
        $this->firebase->tambah('budi@example.test','firebase-password');
        $this->withHeaders(['Origin'=>'http://localhost:5173'])->postJson('/api/v1/daftar',[
            'nip'=>'12345678','nama_lengkap'=>'  budi   santoso ','email'=>'Budi@example.test','password'=>'LocalPass#123','password_confirmation'=>'LocalPass#123','id_token'=>'token:budi@example.test','captcha_token'=>'token-sah',
        ])->assertCreated()->assertJsonPath('message','Email terverifikasi dan akun berhasil dibuat. Silakan masuk untuk mengajukan role.');
        $user=User::query()->where('pegawai_id',$pegawai->id)->firstOrFail();
        $this->assertTrue($user->aktif);
        $this->assertTrue(Hash::check('LocalPass#123',$user->password_hash));
        $this->assertDatabaseCount('USER_ROLE_SCOPE',0);
        $this->assertDatabaseHas('LOG_AUDIT',['entitas'=>'USERS','entitas_id'=>$user->id,'aksi'=>'CREATE']);
    }

    public function test_role_request_records_selected_field_and_displays_it_in_status(): void
    {
        $satker = UnitOrganisasi::forceCreate(['parent_id' => null, 'kode' => 'SAT-ROLE', 'nama' => 'Satker Role', 'jenis' => 'SATKER', 'aktif' => true]);
        $bidang = UnitOrganisasi::forceCreate(['parent_id' => $satker->id, 'kode' => 'BID-ROLE', 'nama' => 'Bidang Tujuan', 'jenis' => 'BIDANG', 'aktif' => true]);
        $subbidang = UnitOrganisasi::forceCreate(['parent_id' => $bidang->id, 'kode' => 'SUB-ROLE', 'nama' => 'Subbidang Tujuan', 'jenis' => 'SUBBIDANG', 'aktif' => true]);
        $pegawai = Pegawai::forceCreate(['nip' => '123456789012345678', 'nama' => 'Pemohon Role', 'email_kontak' => 'pemohon@example.test', 'jenis_kepegawaian' => 'PNS', 'aktif' => true]);
        PenempatanPegawai::forceCreate(['pegawai_id' => $pegawai->id, 'unit_id' => $satker->id, 'mulai' => today()->toDateString(), 'selesai' => null, 'utama' => true]);
        $user = User::factory()->create(['pegawai_id' => $pegawai->id]);

        $this->actingAs($user)->getJson('/api/v1/unit-pilihan-role?role=KABID')->assertOk()->assertJsonPath('data.0.id', $bidang->id);
        $this->getJson('/api/v1/unit-pilihan-role?role=KASATKER')->assertOk()->assertJsonPath('data.0.id', $satker->id);
        $this->getJson('/api/v1/unit-pilihan-role?role=KASUB')->assertOk()->assertJsonPath('data.0.id', $subbidang->id);
        $this->actingAs($user)->postJson('/api/v1/pengajuan-role', [
            'role_kode' => 'STAFF',
            'informasi_pendukung' => 'Saya akan bertugas pada bidang ini.',
        ])->assertCreated();

        $this->assertDatabaseHas('PENGAJUAN_ROLE', ['user_id' => $user->id, 'unit_id' => $satker->id]);
        $this->getJson('/api/v1/pengajuan-role')->assertOk()->assertJsonPath('data.pengajuan.unit_nama', 'Satker Role');

        $secondPegawai = Pegawai::forceCreate(['nip' => '987654321098765432', 'nama' => 'Pemohon Kasub', 'email_kontak' => 'kasub@example.test', 'jenis_kepegawaian' => 'PNS', 'aktif' => true]);
        PenempatanPegawai::forceCreate(['pegawai_id' => $secondPegawai->id, 'unit_id' => $satker->id, 'mulai' => today()->toDateString(), 'selesai' => null, 'utama' => true]);
        $kasubUser = User::factory()->create(['pegawai_id' => $secondPegawai->id]);
        $this->actingAs($kasubUser)->postJson('/api/v1/pengajuan-role', [
            'role_kode' => 'KASUB',
            'unit_id' => $subbidang->id,
            'informasi_pendukung' => 'Saya akan memimpin subbidang ini.',
        ])->assertCreated();
        $this->assertDatabaseHas('PENGAJUAN_ROLE', ['user_id' => $kasubUser->id, 'unit_id' => $subbidang->id]);
    }
}
