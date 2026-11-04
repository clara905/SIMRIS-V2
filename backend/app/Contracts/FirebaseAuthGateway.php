<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Firebase\FirebaseAuthException;
use App\Support\Firebase\FirebaseIdentity;

interface FirebaseAuthGateway
{
    /** Authenticate with the password stored by Firebase. */
    public function signInWithPassword(string $email, string $password): FirebaseIdentity;

    /** Verify the current password, then replace it in Firebase. */
    public function changePassword(string $email, string $currentPassword, string $newPassword): void;

    /** Verify a Firebase ID token and return its verified email identity. */
    public function verifikasiEmail(string $idToken): FirebaseIdentity;

    /** Send Firebase's password reset email without revealing whether the account exists. */
    public function sendPasswordReset(string $email): void;
}
