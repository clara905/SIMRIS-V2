<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CaptchaVerifier;
use App\Contracts\FirebaseAuthGateway;
use App\Exceptions\LoginGagal;
use App\Models\User;
use App\Support\Firebase\FirebaseAuthException;
use App\Support\Firebase\FirebaseIdentity;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Urutan login: captcha → Firebase (kredensial) → pencocokan akun di database
 * → pengecekan aktif dan peran. Otorisasi (peran dan lingkup unit) selalu dari database,
 * tidak dari klaim token Firebase.
 */
final class LoginService
{
    private const PESAN_UMUM = 'Email atau kata sandi salah.';

    private const PESAN_NONAKTIF = 'Akun Anda dinonaktifkan. Hubungi admin satker Anda.';

    public function __construct(
        private readonly CaptchaVerifier $captcha,
        private readonly FirebaseAuthGateway $firebase,
        private readonly AuditService $audit,
    ) {}

    /**
     * @throws ValidationException captcha tidak sah
     * @throws LoginGagal kredensial atau status akun tidak memenuhi syarat
     */
    public function masuk(string $email, string $kataSandi, string $tokenCaptcha, string $ip): User
    {
        $email = mb_strtolower(trim($email));

        if (! $this->captcha->verify($tokenCaptcha, $ip)) {
            throw ValidationException::withMessages([
                'captcha_token' => 'Verifikasi captcha gagal. Muat ulang captcha lalu coba lagi.',
            ]);
        }

        try {
            $identitas = $this->firebase->signInWithPassword($email, $kataSandi);
        } catch (FirebaseAuthException $e) {
            $this->gagalDariFirebase($e, $email, $ip);
        }

        return $this->cocokkanAkun($identitas, $email, $ip);
    }

    /** Selalu diam-diam: tidak membocorkan apakah email terdaftar. */
    public function lupaKataSandi(string $email, string $tokenCaptcha, string $ip): void
    {
        $email = mb_strtolower(trim($email));

        if (! $this->captcha->verify($tokenCaptcha, $ip)) {
            throw ValidationException::withMessages([
                'captcha_token' => 'Verifikasi captcha gagal. Muat ulang captcha lalu coba lagi.',
            ]);
        }

        $user = User::query()->where('email_login', $email)->first();
        if ($user === null || ! $user->aktif) {
            return;
        }

        try {
            $this->firebase->sendPasswordReset($email);
        } catch (FirebaseAuthException $e) {
            Log::warning('Gagal meminta atur ulang kata sandi Firebase', ['alasan' => $e->alasan]);
        }
    }

    private function gagalDariFirebase(FirebaseAuthException $e, string $email, string $ip): never
    {
        $userId = User::query()->where('email_login', $email)->value('id');

        switch ($e->alasan) {
            case FirebaseAuthException::AKUN_DINONAKTIFKAN:
                $this->catatGagal($userId, $email, $ip, 'firebase_akun_dinonaktifkan');
                throw new LoginGagal('firebase_akun_dinonaktifkan', 403, self::PESAN_NONAKTIF);
            case FirebaseAuthException::TERLALU_BANYAK_PERCOBAAN:
                $this->catatGagal($userId, $email, $ip, 'firebase_terlalu_banyak_percobaan');
                throw new LoginGagal('firebase_terlalu_banyak_percobaan', 429, 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');
            case FirebaseAuthException::LAYANAN_GAGAL:
                Log::error('Layanan Firebase Auth gagal saat login', [
                    'email' => $email,
                    'kode_firebase' => $e->kodeFirebase,
                ]);
                throw new LoginGagal('firebase_layanan_gagal', 503, 'Layanan autentikasi sedang bermasalah. Coba lagi nanti.');
            default:
                $this->catatGagal($userId, $email, $ip, 'kredensial_salah');
                throw new LoginGagal('kredensial_salah', 401, self::PESAN_UMUM);
        }
    }

    private function cocokkanAkun(FirebaseIdentity $identitas, string $email, string $ip): User
    {
        if ((bool) config('simris.firebase.wajib_email_terverifikasi') && ! $identitas->emailVerified) {
            $this->catatGagal(User::query()->where('email_login', $email)->value('id'), $email, $ip, 'email_belum_terverifikasi');
            throw new LoginGagal(
                'email_belum_terverifikasi',
                403,
                'Email belum terverifikasi. Gunakan "Lupa kata sandi" untuk mengatur kata sandi Anda.'
            );
        }

        // Kunci pencocokan: email dari Firebase (bukan dari input) ↔ users.email_login.
        $user = User::query()
            ->with('pegawai')
            ->where('email_login', $identitas->email)
            ->first();

        if ($user === null) {
            $this->catatGagal(null, $email, $ip, 'akun_tidak_terdaftar');
            throw new LoginGagal('akun_tidak_terdaftar', 401, self::PESAN_UMUM);
        }

        if (! $user->aktif || ($user->pegawai !== null && ! $user->pegawai->aktif)) {
            $this->catatGagal($user->id, $email, $ip, 'akun_nonaktif');
            throw new LoginGagal('akun_nonaktif', 403, self::PESAN_NONAKTIF);
        }

        if (! $user->penugasanAktif()->exists()) {
            $this->catatGagal($user->id, $email, $ip, 'tanpa_peran_aktif');
            throw new LoginGagal('tanpa_peran_aktif', 403, 'Akun Anda belum memiliki peran aktif. Hubungi admin satker Anda.');
        }

        $this->audit->record('users', $user->id, 'LOGIN', null, ['hasil' => 'berhasil'], $user->id);

        return $user;
    }

    private function catatGagal(?int $userId, string $email, string $ip, string $alasan): void
    {
        $this->audit->record(
            $userId === null ? 'LOGIN' : 'users',
            $userId ?? 0,
            'LOGIN',
            null,
            ['hasil' => 'gagal', 'alasan' => $alasan, 'email' => $email],
            $userId,
        );
    }
}
