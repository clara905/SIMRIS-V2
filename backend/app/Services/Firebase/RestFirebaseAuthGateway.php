<?php

declare(strict_types=1);

namespace App\Services\Firebase;

use App\Contracts\FirebaseAuthGateway;
use App\Support\Firebase\FirebaseAuthException;
use App\Support\Firebase\FirebaseIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Memakai REST API Firebase Authentication (Identity Toolkit) lewat HTTP client Laravel,
 * tanpa paket pihak ketiga. Hanya untuk alur login dan atur ulang kata sandi;
 * membuat atau menonaktifkan akun Firebase (Admin API) dikerjakan di fase pengelolaan user.
 */
final class RestFirebaseAuthGateway implements FirebaseAuthGateway
{
    private const BASE_URL = 'https://identitytoolkit.googleapis.com/v1/';

    public function __construct(private readonly string $apiKey)
    {
    }

    public function signInWithPassword(string $email, string $password): FirebaseIdentity
    {
        $login = $this->post('accounts:signInWithPassword', [
            'email' => $email,
            'password' => $password,
            'returnSecureToken' => true,
        ]);

        $idToken = $login['idToken'] ?? null;
        if (! is_string($idToken) || $idToken === '') {
            throw new FirebaseAuthException(FirebaseAuthException::LAYANAN_GAGAL);
        }

        // Cek ulang token ke Firebase: ambil status akun (disabled, emailVerified).
        $lookup = $this->post('accounts:lookup', ['idToken' => $idToken]);
        $akun = $lookup['users'][0] ?? null;
        if (! is_array($akun) || ! isset($akun['localId'])) {
            throw new FirebaseAuthException(FirebaseAuthException::LAYANAN_GAGAL);
        }
        if (! empty($akun['disabled'])) {
            throw new FirebaseAuthException(FirebaseAuthException::AKUN_DINONAKTIFKAN);
        }

        return new FirebaseIdentity(
            uid: (string) $akun['localId'],
            email: mb_strtolower((string) ($akun['email'] ?? '')),
            emailVerified: (bool) ($akun['emailVerified'] ?? false),
        );
    }

    public function sendPasswordReset(string $email): void
    {
        $this->post('accounts:sendOobCode', [
            'requestType' => 'PASSWORD_RESET',
            'email' => $email,
        ]);
    }

    /** @return array<string, mixed> */
    private function post(string $jalur, array $muatan): array
    {
        if ($this->apiKey === '') {
            throw new FirebaseAuthException(FirebaseAuthException::LAYANAN_GAGAL);
        }

        try {
            $respons = Http::acceptJson()
                ->asJson()
                ->timeout(8)
                ->post(self::BASE_URL.$jalur.'?key='.urlencode($this->apiKey), $muatan);
        } catch (ConnectionException $e) {
            throw new FirebaseAuthException(FirebaseAuthException::LAYANAN_GAGAL, $e);
        }

        if ($respons->successful()) {
            $isi = $respons->json();

            return is_array($isi) ? $isi : [];
        }

        $kode = (string) data_get($respons->json(), 'error.message', '');

        throw new FirebaseAuthException($this->petakanGalat($kode));
    }

    private function petakanGalat(string $kode): string
    {
        // Pesan Firebase bisa berbentuk "KODE : keterangan", jadi cocokkan awalannya.
        foreach (['EMAIL_NOT_FOUND', 'INVALID_PASSWORD', 'INVALID_LOGIN_CREDENTIALS', 'INVALID_EMAIL', 'MISSING_PASSWORD'] as $awalan) {
            if (str_starts_with($kode, $awalan)) {
                return FirebaseAuthException::KREDENSIAL_SALAH;
            }
        }
        if (str_starts_with($kode, 'USER_DISABLED')) {
            return FirebaseAuthException::AKUN_DINONAKTIFKAN;
        }
        if (str_starts_with($kode, 'TOO_MANY_ATTEMPTS_TRY_LATER')) {
            return FirebaseAuthException::TERLALU_BANYAK_PERCOBAAN;
        }

        return FirebaseAuthException::LAYANAN_GAGAL;
    }
}