<?php

declare(strict_types=1);

namespace App\Contracts;

interface CaptchaVerifier
{
    /** True hanya bila token captcha terbukti sah. Gagal menghubungi penyedia = false (fail closed). */
    public function verify(string $token, ?string $ip = null): bool;
}