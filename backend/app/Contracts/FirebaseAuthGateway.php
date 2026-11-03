<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Firebase\FirebaseAuthException;
use App\Support\Firebase\FirebaseIdentity;

interface FirebaseAuthGateway
{
    /**
     * Login ke Firebase Authentication dengan email dan kata sandi.
     *
     * @throws FirebaseAuthException bila kredensial salah, akun dinonaktifkan di Firebase,
     *                               terlalu banyak percobaan, atau layanan tidak tersedia.
     */
    public function signInWithPassword(string $email, string $password): FirebaseIdentity;

    /**
     * Minta Firebase mengirim email atur ulang kata sandi.
     *
     * @throws FirebaseAuthException
     */
    public function sendPasswordReset(string $email): void;
}