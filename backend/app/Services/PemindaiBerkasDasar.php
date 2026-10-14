<?php

namespace App\Services;

use App\Contracts\PemindaiBerkas;
use App\Models\FileLampiran;
use Illuminate\Support\Facades\Storage;

class PemindaiBerkasDasar implements PemindaiBerkas
{
    public function aman(FileLampiran $file): bool
    {
        $disk = Storage::disk(config('lampiran.disk'));
        if (! $disk->exists($file->storage_key)) {
            return false;
        }
        $path = $disk->path($file->storage_key);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $parts = explode('.', $file->nama_asli);
        if (count($parts) !== 2 || ! in_array($mime, config('lampiran.mime.'.$file->ekstensi, []), true)) {
            return false;
        }

        return hash_equals($file->checksum_sha256, hash_file('sha256', $path)) && filesize($path) === $file->ukuran_byte;
    }
}
