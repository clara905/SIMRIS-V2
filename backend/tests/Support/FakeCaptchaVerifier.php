<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\CaptchaVerifier;

final class FakeCaptchaVerifier implements CaptchaVerifier
{
    public bool $lulus = true;

    public function verify(string $token, ?string $ip = null): bool
    {
        return $this->lulus;
    }
}