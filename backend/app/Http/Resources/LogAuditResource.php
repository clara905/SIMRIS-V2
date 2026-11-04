<?php

namespace App\Http\Resources;

use App\Models\LogAudit;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LogAudit */
class LogAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        $actor = $this->resource->user_id
            ? \App\Models\User::query()->with('pegawai')->find($this->resource->user_id)
            : null;
        $data['nama'] = $this->resource->entitas.' #'.$this->resource->entitas_id;
        $data['pelaku'] = $actor?->pegawai?->nama ?? $actor?->username ?? 'Sistem';
        foreach (['data_lama', 'data_baru'] as $field) {
            if (is_array($data[$field])) {
                $data[$field] = app(AuditService::class)->sanitize($data[$field]);
            }
        }

return $data;
    }
}
