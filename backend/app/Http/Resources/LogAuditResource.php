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
        foreach (['data_lama', 'data_baru'] as $field) {
            if (is_array($data[$field])) {
                $data[$field] = app(AuditService::class)->sanitize($data[$field]);
            }
        }

return $data;
    }
}
