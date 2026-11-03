<?php

declare(strict_types=1);

namespace App\Support\Firebase;

use RuntimeException;
use Throwable;

final class FirebaseAuthException extends RuntimeException
{
    public const KREDENSIAL_SALAH = 'kredensial_salah';
    public const AKUN_DINONAKTIFKAN = 'akun_dinonaktifkan';
    public const TERLALU_BANYAK_PERCOBAAN = 'terlalu_banyak_percobaan';
    public const LAYANAN_GAGAL = 'layanan_gagal';

    public function __construct(public readonly string $alasan, ?Throwable $sebelumnya = null)
    {
        parent::__construct("Firebase Auth: {$alasan}", 0, $sebelumnya);
    }
}