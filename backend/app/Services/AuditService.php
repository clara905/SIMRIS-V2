<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditService
{
    /**
     * Record an audit entry using the active transaction and bypass the read-only model.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $entity,
        int $entityId,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?int $actorId = null,
    ): void {
        DB::table('LOG_AUDIT')->insert([
            'user_id' => $actorId ?? Auth::id(),
            'entitas' => $entity,
            'entitas_id' => $entityId,
            'aksi' => $action,
            'data_lama' => $before === null ? null : json_encode($this->sanitize($before), JSON_THROW_ON_ERROR),
            'data_baru' => $after === null ? null : json_encode($this->sanitize($after), JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip() ?? 'cli',
            'waktu' => now(),
        ]);
    }

    /**
     * Remove sensitive fields recursively before data is stored or returned.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $sensitiveKey = '/password|token|secret|cookie|nip|isi_file|isi_teks|credential|api_key/i';

        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match($sensitiveKey, $key) === 1) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
