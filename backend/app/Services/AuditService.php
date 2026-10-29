<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

class AuditService
{
    public function record(string $entity, int $id, string $action, ?array $before = null, ?array $after = null, ?int $actor = null): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Audit harus ditulis dalam transaksi perubahan data.');
        }
        DB::table('LOG_AUDIT')->insert(['user_id' => $actor ?? auth()->id(), 'entitas' => $entity, 'entitas_id' => $id, 'aksi' => $action, 'data_lama' => $before === null ? null : json_encode($this->sanitize($before), JSON_THROW_ON_ERROR), 'data_baru' => $after === null ? null : json_encode($this->sanitize($after), JSON_THROW_ON_ERROR), 'ip_address' => request()->ip() ?? '127.0.0.1', 'waktu' => now()]);
    }

    public function sanitize(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            if (preg_match('/password|hash|token|secret|totp|authorization|cookie|nip|isi_file|isi_teks|file_content|contents|data_mentah/i', (string) $key)) {
                continue;
            }
            if (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
