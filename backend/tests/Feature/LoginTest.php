<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MembuatPengguna;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use MembuatPengguna, RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->siapkan(); }

    private function login(array $payload = [])
    {
        return $this->withHeaders(['Origin'=>'http://localhost:5173'])->postJson('/api/v1/login', $payload + ['email'=>'budi@simris.test','password'=>'Rahasia#123','captcha_token'=>'token-sah']);
    }

    public function test_login_authenticates_with_firebase_and_opens_sanctum_session(): void
    {
        $user=$this->buatPengguna();
        $this->login()->assertOk()->assertJsonPath('data.email','budi@simris.test')->assertJsonMissingPath('data.password_hash');
        $this->assertAuthenticatedAs($user,'web');
        $this->assertSame(1,$this->firebase->percobaan);
    }

    public function test_nip_can_be_used_for_login_and_wrong_credentials_are_generic(): void
    {
        $user=$this->buatPengguna();
        $this->login(['email'=>$user->pegawai->nip])->assertOk();
        auth()->logout();
        $this->login(['password'=>'wrong'])->assertUnauthorized()->assertJsonPath('message','NIP/email atau kata sandi salah.');
        $this->login(['email'=>'unknown@example.test'])->assertUnauthorized()->assertJsonPath('message','NIP/email atau kata sandi salah.');
    }

    public function test_correct_password_for_pending_account_reports_approval_status(): void
    {
        $user=$this->buatPengguna(['aktif'=>false]);
        $this->login()->assertForbidden()->assertJsonPath('message','Akun menunggu persetujuan Admin Satker.');
        $this->login(['password'=>'wrong'])->assertUnauthorized()->assertJsonPath('message','NIP/email atau kata sandi salah.');
    }

    public function test_forgot_password_requires_verified_email_token_and_updates_local_hash(): void
    {
        $user=$this->buatPengguna();
        $this->postJson('/api/v1/lupa-kata-sandi',['email'=>'budi@simris.test','captcha_token'=>'token-sah'])->assertAccepted();
        $this->assertSame(['budi@simris.test'],$this->firebase->resetDikirim);
    }
}
