<?php

namespace App\Contracts;

use App\Models\FileLampiran;

interface PemindaiBerkas
{
    public function aman(FileLampiran $file): bool;
}
