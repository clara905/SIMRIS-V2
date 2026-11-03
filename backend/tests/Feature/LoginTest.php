<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\MembuatPengguna;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use MembuatPengguna;
    use RefreshDatabase;

    private const PESAN_UMUM = 'Email atau kata sandi salah.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    /** Referer dari domain "stateful" membuat Sanctum menyalakan sesi. */
    private function kirim(array $isi = []): TestResponse
    {
        return $this->withHeaders(['Referer' => 'http://localhost'])->postJson('/api/v1/login', $isi + [
            'email' => 'budi@simris.test',
            'password' => 'Rahasia#123',
            'captcha_token' => 'token-sah',
        ]);
    }

    private function logTerakhir(): LogAudit
    {
        return LogAudit::query()->latest('id')->firstOrFail();
    }

    public function test_config_recaptcha_publik_hanya_mengembalikan_site_key(): void
    {
        config([
            'simris.captcha.site_key' => 'site-key-publik',
            'simris.captcha.secret' => 'secret-key-rahasia',
        ]);

        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertExactJson(['recaptcha_site_key' => 'site-key-publik'])
            ->assertJsonMissing(['secret-key-rahasia']);
    }

    public function test_login_berhasil_mengembalikan_pengguna_dan_peran(): void
    {
        $user = $this->buatPengguna();

        $this->kirim()
            ->assertOk()
            ->assertJsonPath('data.email', 'budi@simris.test')
            ->assertJsonPath('data.nama', 'Budi Santoso')
            ->assertJsonPath('data.peran.0.kode', 'STAFF')
            ->assertJsonPath('data.peran.0.unit.kode', 'SAT01')
            ->assertJsonMissingPath('data.password_hash');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('berhasil', $this->logTerakhir()->data_baru['hasil']);
    }

    public function test_email_tidak_membedakan_huruf_besar_kecil(): void
    {
        $this->buatPengguna();

        $this->kirim(['email' => 'BUDI@Simris.Test'])->assertOk();
    }

    public function test_captcha_gagal_ditolak_sebelum_menyentuh_firebase(): void
    {
        $this->buatPengguna();
        $this->captcha->lulus = false;

        $this->kirim()->assertStatus(422)->assertJsonValidationErrors('captcha_token');

        $this->assertSame(0, $this->firebase->percobaan);
        $this->assertGuest('web');
    }

    public function test_kata_sandi_salah_dan_email_tak_dikenal_memberi_pesan_sama(): void
    {
        $user = $this->buatPengguna();

        $salah = $this->kirim(['password' => 'salah'])->assertStatus(401)->assertJsonPath('message', self::PESAN_UMUM);
        $log = $this->logTerakhir();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('kredensial_salah', $log->data_baru['alasan']);

        $takDikenal = $this->kirim(['email' => 'hantu@simris.test'])->assertStatus(401);
        $this->assertSame($salah->json('message'), $takDikenal->json('message'));
        $log = $this->logTerakhir();
        $this->assertNull($log->user_id);
        $this->assertSame(0, $log->entitas_id);

        $this->assertGuest('web');
    }

    public function test_akun_ada_di_firebase_tetapi_tidak_ada_di_database_ditolak(): void
    {
        $this->firebase->tambah('hantu@simris.test', 'Rahasia#123');

        $this->kirim(['email' => 'hantu@simris.test'])->assertStatus(401)->assertJsonPath('message', self::PESAN_UMUM);

        $this->assertSame('akun_tidak_terdaftar', $this->logTerakhir()->data_baru['alasan']);
        $this->assertGuest('web');
    }

    public function test_user_nonaktif_ditolak(): void
    {
        $this->buatPengguna(['aktif' => false]);

        $this->kirim()->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_pegawai_nonaktif_ditolak(): void
    {
        $this->buatPengguna(['pegawai_aktif' => false]);

        $this->kirim()->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_user_tanpa_peran_aktif_ditolak(): void
    {
        $this->buatPengguna(['selesai' => now()->subHour()]);
        $this->kirim()->assertStatus(403);

        $this->buatPengguna(['email' => 'sari@simris.test', 'mulai' => now()->addDay()]);
        $this->kirim(['email' => 'sari@simris.test'])->assertStatus(403);

        $this->assertGuest('web');
    }

    public function test_email_firebase_belum_terverifikasi_ditolak_kecuali_dimatikan(): void
    {
        $this->buatPengguna(['verified' => false]);

        $this->kirim()->assertStatus(403);

        config(['simris.firebase.wajib_email_terverifikasi' => false]);
        $this->kirim()->assertOk();
    }

    public function test_akun_firebase_yang_dinonaktifkan_ditolak(): void
    {
        $this->buatPengguna();
        $this->firebase->tambah('budi@simris.test', 'Rahasia#123', true, true);

        $this->kirim()->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_percobaan_login_dibatasi(): void
    {
        $this->buatPengguna();

        for ($i = 0; $i < 5; $i++) {
            $this->kirim(['password' => 'salah'])->assertStatus(401);
        }

        $this->kirim(['password' => 'salah'])->assertStatus(429);
    }

    public function test_permintaan_tanpa_sesi_stateful_ditolak(): void
    {
        $this->buatPengguna();

        $this->postJson('/api/v1/login', [
            'email' => 'budi@simris.test',
            'password' => 'Rahasia#123',
            'captcha_token' => 'token-sah',
        ])->assertStatus(400);
    }

    public function test_validasi_input(): void
    {
        $this->kirim(['email' => 'bukan-email', 'password' => '', 'captcha_token' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password', 'captcha_token']);
    }

    public function test_me_butuh_login_dan_mengembalikan_peran(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();

        $user = $this->buatPengguna();
        $this->actingAs($user, 'web')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.peran.0.kode', 'STAFF');
    }

    public function test_akun_yang_dinonaktifkan_setelah_login_ditolak_dan_dikeluarkan(): void
    {
        $user = $this->buatPengguna();
        $user->aktif = false;
        $user->save();

        $this->actingAs($user, 'web')->getJson('/api/v1/me')->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_logout(): void
    {
        $user = $this->buatPengguna();

        $this->actingAs($user, 'web')
            ->withHeaders(['Referer' => 'http://localhost'])
            ->postJson('/api/v1/logout')
            ->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_lupa_kata_sandi_tidak_membocorkan_keberadaan_email(): void
    {
        $this->buatPengguna();

        $ada = $this->postJson('/api/v1/lupa-kata-sandi', ['email' => 'budi@simris.test', 'captcha_token' => 'x'])->assertStatus(202);
        $tidakAda = $this->postJson('/api/v1/lupa-kata-sandi', ['email' => 'hantu@simris.test', 'captcha_token' => 'x'])->assertStatus(202);

        $this->assertSame($ada->json('message'), $tidakAda->json('message'));
        $this->assertSame(['budi@simris.test'], $this->firebase->resetDikirim);
    }

    public function test_lupa_kata_sandi_tidak_mengirim_untuk_akun_nonaktif_atau_captcha_gagal(): void
    {
        $this->buatPengguna(['aktif' => false]);
        $this->postJson('/api/v1/lupa-kata-sandi', ['email' => 'budi@simris.test', 'captcha_token' => 'x'])->assertStatus(202);
        $this->assertSame([], $this->firebase->resetDikirim);

        $this->captcha->lulus = false;
        $this->postJson('/api/v1/lupa-kata-sandi', ['email' => 'budi@simris.test', 'captcha_token' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('captcha_token');
    }
}