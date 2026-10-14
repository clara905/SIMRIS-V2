<?php

namespace App\Services;

use App\Contracts\AsetSyncDriver;
use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Http;

class AsetApiDriver implements AsetSyncDriver
{
    public function fetch(): array
    {
        $url = config('aset.url');
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new BusinessRuleException('konfigurasi_aset_belum_lengkap', 'Alamat API aset belum dikonfigurasi dengan benar.');
        }
        try {
            $request = Http::acceptJson()->timeout(30)->connectTimeout(10)->retry([1000, 2000], throw: false);
            if (config('aset.token')) {
                $request = $request->withToken(config('aset.token'));
            }
            $response = $request->get($url);
            if (! $response->successful()) {
                throw new \RuntimeException('API gagal');
            }
            $body = $response->json();
        } catch (\Throwable $e) {
            throw new BusinessRuleException('api_aset_gagal', 'API aset tidak dapat dibaca; periksa konfigurasi dan koneksi.');
        }
        if (! is_array($body) || ($body['success'] ?? null) !== true || ! isset($body['data']) || ! is_array($body['data']) || ! array_is_list($body['data']) || $body['data'] === []) {
            throw new BusinessRuleException('respons_aset_tidak_valid', 'Respons API harus sukses dan memiliki daftar data yang tidak kosong.');
        }
        if (isset($body['links']['next']) || isset($body['next_page_url']) || ($body['meta']['last_page'] ?? 1) > 1 || ($body['last_page'] ?? 1) > 1) {
            throw new BusinessRuleException('api_aset_berpaginasi', 'API terindikasi berpaginasi; konfigurasi memerlukan keputusan pengguna.');
        }

        return $body['data'];
    }
}
