<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Ancaman;
use App\Models\JenisAncaman;
use App\Models\JenisKerentanan;
use App\Models\KategoriAset;
use App\Models\Kerentanan;
use App\Models\KlasifikasiInformasi;
use App\Models\Kontrol;
use App\Models\Regulasi;
use App\Models\StandarKontrol;
use App\Models\SumberAncaman;
use App\Models\SumberAset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KatalogService
{
    public const MODELS = ['jenis-ancaman' => JenisAncaman::class, 'sumber-ancaman' => SumberAncaman::class, 'ancaman' => Ancaman::class, 'jenis-kerentanan' => JenisKerentanan::class, 'kerentanan' => Kerentanan::class, 'standar-kontrol' => StandarKontrol::class, 'kontrol' => Kontrol::class, 'regulasi' => Regulasi::class, 'sumber-aset' => SumberAset::class, 'kategori-aset' => KategoriAset::class, 'klasifikasi-informasi' => KlasifikasiInformasi::class];

    public function rules(string $resource, ?int $id = null): array
    {
        $class = self::MODELS[$resource];
        $model = new $class;
        $rules = [];
        foreach ($model->getFillable() as $field) {
            $rule = [$id ? 'sometimes' : 'required', 'string', 'max:255'];
            if (in_array($field, ['deskripsi', 'referensi'], true)) {
                $rule = [$id ? 'sometimes' : 'required', 'string', 'max:10000'];
            }
            if ($field === 'kode' && $resource !== 'kontrol') {
                $rule[] = Rule::unique($model->getTable(), 'kode')->ignore($id);
            }
            $foreign = ['jenis_ancaman_id' => 'JENIS_ANCAMAN', 'jenis_kerentanan_id' => 'JENIS_KERENTANAN', 'standar_id' => 'STANDAR_KONTROL', 'parent_id' => $model->getTable()];
            if (isset($foreign[$field])) {
                $rule = [$field === 'parent_id' ? 'sometimes' : ($id ? 'sometimes' : 'required'), $field === 'parent_id' ? 'nullable' : 'required', 'integer', Rule::exists($foreign[$field], 'id')];
            }
            if ($field === 'jenis') {
                $rule = [$id ? 'sometimes' : 'required', Rule::in(['BAGIAN', 'OBJEKTIF', 'KONTROL'])];
            }
            $rules[$field] = $rule;
        }
        if ($resource === 'ancaman') {
            $rules['sumber_ids'] = ['sometimes', 'array', 'max:100'];
            $rules['sumber_ids.*'] = ['integer', 'distinct', Rule::exists('SUMBER_ANCAMAN', 'id')];
        }

        return $rules;
    }

    public function filtered(Builder $query, array $input, array $allowed, bool $timestamps = true): Builder
    {
        foreach ($input['filter'] ?? [] as $key => $value) {
            if (! in_array($key, $allowed, true) || ! is_scalar($value)) {
                throw ValidationException::withMessages(['filter' => ['Kolom atau nilai filter tidak diperbolehkan.']]);
            } $query->where($key, $value);
        }
        $sort = $input['sort'] ?? 'id';
        $column = ltrim($sort, '-');
        if (! in_array($column, [...$allowed, ...($timestamps ? ['created_at', 'updated_at'] : [])], true)) {
            throw ValidationException::withMessages(['sort' => ['Kolom urutan tidak diperbolehkan.']]);
        }

        return $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id');
    }

    public function query(string $resource, array $input = []): Builder
    {
        Gate::authorize('katalog.view');
        $class = self::MODELS[$resource];

        return $this->filtered($class::query(), $input, ['id', ...(new $class)->getFillable()]);
    }

    public function mutate(string $resource, ?int $id, array $data, string $action, User $actor): Model
    {
        try {
            return DB::transaction(function () use ($resource, $id, $data, $action, $actor): Model {
                DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
                Gate::forUser($actor->refresh())->authorize('katalog.write');
                $class = self::MODELS[$resource];
                $model = $id ? $class::whereKey($id)->lockForUpdate()->firstOrFail() : new $class;
                if ($action === 'delete') {
                    $model->delete();

                    return $model;
                }
                $sources = $data['sumber_ids'] ?? null;
                unset($data['sumber_ids']);
                $model->fill($data);
                if (in_array($resource, ['kontrol', 'kategori-aset'], true)) {
                    $this->validateHierarchy($model);
                }
                $model->save();
                if ($sources !== null) {
                    $before = DB::table('ANCAMAN_SUMBER')->where('ancaman_id', $model->id)->pluck('sumber_ancaman_id')->all();
                    DB::table('ANCAMAN_SUMBER')->where('ancaman_id', $model->id)->delete();
                    foreach ($sources as $source) {
                        DB::table('ANCAMAN_SUMBER')->insert(['ancaman_id' => $model->id, 'sumber_ancaman_id' => $source, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    app(AuditService::class)->record('ANCAMAN_SUMBER', $model->id, 'UPDATE', ['sumber_ids' => $before], ['sumber_ids' => $sources]);
                }

                return $model;
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23503', '23505'], true)) {
                throw new BusinessRuleException('integritas_data', 'Data duplikat atau masih dirujuk data lain.');
            } throw $e;
        }
    }

    private function validateHierarchy(Model $model): void
    {
        $seen = [];
        $cursor = $model->parent_id;
        while ($cursor) {
            if ((int) $cursor === (int) $model->id || isset($seen[$cursor])) {
                throw new BusinessRuleException('siklus_hierarki', 'Induk tersebut membentuk siklus hierarki.');
            } $seen[$cursor] = true;
            $parent = $model->newQuery()->findOrFail($cursor);
            if ($model instanceof Kontrol && (int) $parent->standar_id !== (int) $model->standar_id) {
                throw new BusinessRuleException('standar_berbeda', 'Induk kontrol harus dari standar yang sama.');
            } $cursor = $parent->parent_id;
        }
        if ($model instanceof Kontrol && $model->exists && Kontrol::where('parent_id', $model->id)->where('standar_id','!=',$model->standar_id)->exists()) {
            throw new BusinessRuleException('standar_berbeda','Anak kontrol harus dari standar yang sama.');
        }
    }
}
