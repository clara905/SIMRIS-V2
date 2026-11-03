<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use App\Contracts\CaptchaVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Verifikasi token Google reCAPTCHA (v2 checkbox; v3 bila min_score diisi) di sisi server. */
final class RecaptchaVerifier implements CaptchaVerifier
{
    public function __construct(
        private readonly string $secret,
        private readonly string $verifyUrl,
        private readonly ?string $hostname = null,
        private readonly ?float $minScore = null,
    ) {
    }

    public function verify(string $token, ?string $ip = null): bool
    {
        if ($this->secret === '' || $token === '') {
            return false;
        }

        try {
            $respons = Http::asForm()->timeout(8)->post($this->verifyUrl, array_filter([
                'secret' => $this->secret,
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (ConnectionException) {
            return false;
        }

        if (! $respons->successful() || $respons->json('success') !== true) {
            return false;
        }
        if ($this->hostname !== null && $respons->json('hostname') !== $this->hostname) {
            return false;
        }
        if ($this->minScore !== null && (float) $respons->json('score', 0) < $this->minScore) {
            return false;
        }

        return true;
    }
}