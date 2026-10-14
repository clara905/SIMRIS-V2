<?php

namespace App\Models\Concerns;

use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => app(AuditService::class)->record($model->getTable(), (int) $model->getKey(), 'CREATE', null, $model->getAttributes()));
        static::updated(fn ($model) => app(AuditService::class)->record($model->getTable(), (int) $model->getKey(), 'UPDATE', $model->getRawOriginal(), $model->getAttributes()));
        static::deleted(fn ($model) => app(AuditService::class)->record($model->getTable(), (int) $model->getKey(), 'DELETE', $model->getAttributes()));
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(fn () => parent::save($options));
    }

    public function delete(): ?bool
    {
        return DB::transaction(fn () => parent::delete());
    }
}
