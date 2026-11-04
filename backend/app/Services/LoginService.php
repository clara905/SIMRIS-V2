<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CaptchaVerifier;
use App\Contracts\FirebaseAuthGateway;
use App\Exceptions\LoginGagal;
use App\Models\User;
use App\Support\Firebase\FirebaseAuthException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LoginService
{
    public function __construct(private readonly CaptchaVerifier $captcha, private readonly FirebaseAuthGateway $firebase, private readonly AuditService $audit) {}

    public function masuk(string $identifier, string $password, string $captchaToken, string $ip): User
    {
        $this->checkCaptcha($captchaToken, $ip);
        $identifier = mb_strtolower(trim($identifier));
        $user = User::query()->with('pegawai')->where('email_login', $identifier)->orWhereHas('pegawai', fn($q) => $q->where('nip', $identifier))->first();
        if (!$user) {
            throw new LoginGagal('kredensial_salah', 401, 'NIP/email atau kata sandi salah.');
        }
        try {
            $identity = $this->firebase->signInWithPassword($user->email_login, $password);
        } catch (FirebaseAuthException $e) {
            if ($e->alasan === FirebaseAuthException::KREDENSIAL_SALAH) throw new LoginGagal('kredensial_salah', 401, 'NIP/email atau kata sandi salah.');
            Log::error('Firebase Auth gagal saat login', ['reason'=>$e->alasan, 'firebase_code'=>$e->kodeFirebase]);
            throw new LoginGagal('firebase_unavailable', 503, 'Layanan autentikasi sedang bermasalah. Coba lagi nanti.');
        }
        if ((bool)config('simris.firebase.wajib_email_terverifikasi') && !$identity->emailVerified) throw new LoginGagal('email_unverified', 403, 'Email belum terverifikasi.');
        if (!$user->aktif || ($user->pegawai !== null && !$user->pegawai->aktif)) {
            throw new LoginGagal('akun_nonaktif', 403, 'Akun tidak aktif. Hubungi administrator.');
        }
        $this->audit->record('USERS', $user->id, 'LOGIN', null, ['hasil' => 'berhasil'], $user->id);
        return $user;
    }

    public function daftar(array $data, string $ip): User
    {
        $this->checkCaptcha($data['captcha_token'], $ip);
        $email = mb_strtolower(trim($data['email']));
        $pegawai = $this->validasiPendaftaran($data['nip'], $data['nama_lengkap']);
        try { $identity = $this->firebase->verifikasiEmail($data['id_token']); }
        catch (FirebaseAuthException $e) { Log::notice('Token verifikasi email Firebase ditolak', ['reason' => $e->alasan]); throw ValidationException::withMessages(['id_token' => 'Verifikasi email gagal atau tautan sudah kedaluwarsa.']); }
        if (!$identity->emailVerified || $identity->email !== $email) throw ValidationException::withMessages(['id_token' => 'Email belum terverifikasi atau tidak cocok dengan email pendaftaran.']);
        return DB::transaction(function() use ($pegawai, $email, $data) {
            $locked = DB::table('PEGAWAI')->where('id', $pegawai->id)->lockForUpdate()->first();
            if (User::query()->where('pegawai_id', $locked->id)->exists()) throw ValidationException::withMessages(['nip' => "NIP {$locked->nip} sudah memiliki akun terdaftar."]);
            // NIP tetap menjadi identitas pegawai di PEGAWAI.nip; username akun memakai email login.
            $user = User::create(['pegawai_id' => $locked->id, 'username' => $email, 'email_login' => $email, 'password_hash' => $data['password'], 'aktif' => true, 'wajib_ganti_password' => false]);
            $this->audit->record('USERS', $user->id, 'CREATE', null, ['pegawai_id' => $user->pegawai_id, 'email_login' => $email, 'aktif' => true]);
            return $user;
        });
    }

    public function validasiPendaftaran(string $nip, string $namaLengkap): object
    {
        $nip = trim($nip);
        if (! preg_match('/^[0-9]{18}$/', $nip)) {
            throw ValidationException::withMessages(['nip' => 'NIP harus terdiri dari tepat 18 digit angka.']);
        }

        $pegawai = DB::table('PEGAWAI')->where('nip', $nip)->first();
        if (! $pegawai) {
            throw ValidationException::withMessages(['nip' => 'NIP tidak ditemukan di data pegawai.']);
        }
        if (User::query()->where('pegawai_id', $pegawai->id)->exists()) {
            throw ValidationException::withMessages(['nip' => 'NIP sudah memiliki akun terdaftar.']);
        }
        if (! $pegawai->aktif) {
            throw ValidationException::withMessages(['nip' => 'Pegawai nonaktif tidak dapat mendaftar.']);
        }

        $normalize = fn (string $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? '');
        if ($normalize($pegawai->nama) !== $normalize($namaLengkap)) {
            throw ValidationException::withMessages(['nama_lengkap' => 'Nama tidak sesuai dengan data pegawai.']);
        }

        $hasActivePlacement = DB::table('PENEMPATAN_PEGAWAI')
            ->where('pegawai_id', $pegawai->id)
            ->where('mulai', '<=', today())
            ->where(fn ($query) => $query->whereNull('selesai')->orWhere('selesai', '>=', today()))
            ->exists();
        if (! $hasActivePlacement) {
            throw ValidationException::withMessages(['nip' => 'Pegawai belum memiliki penempatan aktif.']);
        }

        return $pegawai;
    }

    public function lupaKataSandi(array $data, string $ip): void
    {
        $this->checkCaptcha($data['captcha_token'], $ip);
        $email = mb_strtolower(trim($data['email']));
        try { $this->firebase->sendPasswordReset($email); }
        catch (FirebaseAuthException $e) {
            if ($e->alasan !== FirebaseAuthException::KREDENSIAL_SALAH) Log::warning('Firebase password reset service unavailable', ['reason'=>$e->alasan]);
        }
    }

    private function checkCaptcha(string $token, string $ip): void
    {
        if (!$this->captcha->verify($token, $ip)) throw ValidationException::withMessages(['captcha_token' => 'Verifikasi captcha gagal. Muat ulang captcha lalu coba lagi.']);
    }
}
