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

    public function test_verified_staff_registration_creates_inactive_local_password_without_scope(): void
    {
        $unit = UnitOrganisasi::forceCreate(['parent_id'=>null,'kode'=>'TEST-SAT','nama'=>'Satker Test','jenis'=>'SATKER','aktif'=>true]);
        $pegawai = Pegawai::forceCreate(['nip'=>'12345678','nama'=>'Budi Santoso','email_kontak'=>'kontak@example.test','jenis_kepegawaian'=>'PNS','aktif'=>true]);
        PenempatanPegawai::forceCreate(['pegawai_id'=>$pegawai->id,'unit_id'=>$unit->id,'mulai'=>now()->toDateString(),'selesai'=>null,'utama'=>true]);
        $this->firebase->tambah('budi@example.test','firebase-password');
        $this->withHeaders(['Referer'=>'http://localhost'])->postJson('/api/v1/daftar',[
            'nip'=>'12345678','nama_lengkap'=>'  budi   santoso ','email'=>'Budi@example.test','password'=>'LocalPass#123','password_confirmation'=>'LocalPass#123','id_token'=>'token:budi@example.test','captcha_token'=>'token-sah',
        ])->assertCreated()->assertJsonPath('message','Pendaftaran diterima dan menunggu persetujuan Admin Satker.');
        $user=User::query()->where('pegawai_id',$pegawai->id)->firstOrFail();
        $this->assertFalse($user->aktif);
        $this->assertTrue(Hash::check('LocalPass#123',$user->password_hash));
        $this->assertDatabaseCount('USER_ROLE_SCOPE',0);
        $this->assertDatabaseHas('LOG_AUDIT',['entitas'=>'USERS','entitas_id'=>$user->id,'aksi'=>'CREATE']);
    }
}
