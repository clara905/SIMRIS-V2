<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Kegagalan login yang dirender sebagai JSON dengan pesan yang aman ditampilkan ke pengguna. */
final class LoginGagal extends RuntimeException
{
    public function __construct(
        public readonly string $alasan,
        public readonly int $status,
        string $pesan,
    ) {
        parent::__construct($pesan);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}