<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\FirebaseAuthGateway;
use App\Support\Firebase\FirebaseAuthException;
use App\Support\Firebase\FirebaseIdentity;

/** Pengganti Firebase untuk test: tanpa jaringan. */
final class FakeFirebaseAuthGateway implements FirebaseAuthGateway
{
    /** @var array<string, array{password: string, verified: bool, disabled: bool}> */
    private array $akun = [];

    public int $percobaan = 0;

    /** @var list<string> */
    public array $resetDikirim = [];

    public function tambah(string $email, string $password, bool $verified = true, bool $disabled = false): void
    {
        $this->akun[mb_strtolower($email)] = compact('password', 'verified', 'disabled');
    }

    public function verifikasiEmail(string $idToken): FirebaseIdentity
    {
        $this->percobaan++;
        $email = mb_strtolower(str_starts_with($idToken, 'token:') ? substr($idToken, 6) : '');
        $a = $this->akun[$email] ?? null;

        if ($a === null) {
            throw new FirebaseAuthException(FirebaseAuthException::KREDENSIAL_SALAH);
        }
        if ($a['disabled']) {
            throw new FirebaseAuthException(FirebaseAuthException::AKUN_DINONAKTIFKAN);
        }

        return new FirebaseIdentity('uid-'.md5($email), $email, $a['verified']);
    }

    public function signInWithPassword(string $email, string $password): FirebaseIdentity
    {
        $this->percobaan++;
        $email = mb_strtolower($email);
        $account = $this->akun[$email] ?? null;
        if (!$account || $account['password'] !== $password) throw new FirebaseAuthException(FirebaseAuthException::KREDENSIAL_SALAH);
        if ($account['disabled']) throw new FirebaseAuthException(FirebaseAuthException::AKUN_DINONAKTIFKAN);
        return new FirebaseIdentity('uid-'.md5($email),$email,$account['verified']);
    }

    public function sendPasswordReset(string $email): void
    {
        $email=mb_strtolower($email);
        if (!isset($this->akun[$email])) throw new FirebaseAuthException(FirebaseAuthException::KREDENSIAL_SALAH);
        $this->resetDikirim[]=$email;
    }

    public function changePassword(string $email, string $currentPassword, string $newPassword): void
    {
        $email = mb_strtolower($email);
        $account = $this->akun[$email] ?? null;
        if (!$account || $account['password'] !== $currentPassword) throw new FirebaseAuthException(FirebaseAuthException::KREDENSIAL_SALAH);
        $this->akun[$email]['password'] = $newPassword;
    }

}
