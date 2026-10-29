<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Kontrol;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TautanKepatuhanService
{
    /** Table, source column, target resource, target column, writable attributes. */
    public const LINKS = [
        'kontrol-internal' => ['standar' => ['KONTROL_INTERNAL_STANDAR', 'kontrol_internal_id', 'kontrol', 'kontrol_id', ['keterangan']], 'sop' => ['KONTROL_INTERNAL_SOP', 'kontrol_internal_id', 'sop-versi', 'sop_versi_id', []]],
        'sop-versi' => ['kontrol' => ['SOP_KONTROL', 'sop_versi_id', 'kontrol', 'kontrol_id', []]],
        'soa-detail' => ['sop' => ['SOA_DETAIL_SOP', 'soa_detail_id', 'sop-versi', 'sop_versi_id', []], 'risiko' => ['SOA_DETAIL_RISIKO', 'soa_detail_id', 'risiko', 'risiko_id', []], 'bukti' => ['BUKTI_SOA', 'soa_detail_id', 'dokumen-versi', 'dokumen_versi_id', ['keterangan']]],
        'risiko' => ['kontrol' => ['RISIKO_KONTROL', 'risiko_id', 'kontrol', 'kontrol_id', ['alasan_relevansi']], 'kontrol-internal' => ['RISIKO_KONTROL_INTERNAL', 'risiko_id', 'kontrol-internal', 'kontrol_internal_id', ['status_penerapan', 'kondisi_penerapan', 'diperiksa_pada']], 'sop' => ['RISIKO_KONTROL_SOP', 'risiko_id', 'sop-versi', 'sop_versi_id', ['kontrol_internal_id']]],
        'rencana-penanganan' => ['sop' => ['PENANGANAN_SOP', 'rencana_penanganan_id', 'sop-versi', 'sop_versi_id', []]],
    ];

    public function source(string $resource, int $id, User $actor, bool $write = false): Model
    {
        if ($resource === 'risiko') {
            $model = app(AsesmenService::class)->risks($actor)->findOrFail($id);
            if ($write) {
                app(AsesmenService::class)->editable($model->asesmen_id, $actor);
            }

            return $model;
        }
        if ($resource === 'rencana-penanganan') {
            $model = app(PenangananService::class)->plans($actor)->findOrFail($id);
            if ($write) {
                $assessment = app(AsesmenService::class)->assessments($actor)->whereKey($model->asesmen_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('asesmen.write', [$assessment]);
                if ($assessment->status !== 'PENANGANAN' || $assessment->dibekukan_pada) {
                    throw new BusinessRuleException('bukan_tahap_penanganan', 'Tautan penanganan hanya dapat diubah pada tahap PENANGANAN.');
                }
            }

            return $model;
        }
        $service = app(KepatuhanService::class);
        $model = $service->query($resource, $actor)->findOrFail($id);
        if ($write) {
            Gate::forUser($actor)->authorize('kepatuhan.write', [$service->unit($model), false, $resource]);
            $service->draft($model);
        }

        return $model;
    }

    public function mutate(string $resource, int $id, string $relation, array $data, User $actor, bool $delete = false): array
    {
        return DB::transaction(function () use ($resource, $id, $relation, $data, $actor, $delete): array {
            $this->source($resource, $id, $actor, true);
            [$table,$source,$target,$column,$fields] = self::LINKS[$resource][$relation];
            $targetId = (int) $data[$column];
            if ($target === 'kontrol') {
                Kontrol::findOrFail($targetId);
            } elseif ($target === 'dokumen-versi') {
                app(DokumenService::class)->finalVersion($targetId, $actor);
            } elseif ($target === 'risiko') {
                app(AsesmenService::class)->risks($actor)->findOrFail($targetId);
            } else {
                $model = app(KepatuhanService::class)->query($target, $actor)->findOrFail($targetId);
                if ($target === 'sop-versi' && $resource !== 'sop-versi') {
                    if ($model->status !== 'DISAHKAN') {
                        throw new BusinessRuleException('sop_belum_disahkan', 'Tautan operasional memerlukan versi SOP DISAHKAN.');
                    } app(DokumenService::class)->finalVersion($model->dokumen_versi_id, $actor);
                }
            }
            if (isset($data['diperiksa_pada'])) {
                $data['diperiksa_pada'] = Carbon::parse($data['diperiksa_pada'])->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
            }
            $keys = [$source => $id, $column => $targetId];
            if ($table === 'RISIKO_KONTROL_SOP') {
                $internal = (int) $data['kontrol_internal_id'];
                app(KepatuhanService::class)->query('kontrol-internal', $actor)->findOrFail($internal);
                if (! DB::table('RISIKO_KONTROL_INTERNAL')->where('risiko_id', $id)->where('kontrol_internal_id', $internal)->exists() || ! DB::table('KONTROL_INTERNAL_SOP')->where('kontrol_internal_id', $internal)->where('sop_versi_id', $targetId)->exists()) {
                    throw new BusinessRuleException('tautan_sop_tidak_valid', 'Risiko dan SOP harus merujuk kontrol internal yang sama.');
                } $keys['kontrol_internal_id'] = $internal;
            }
            $old = DB::table($table)->where($keys)->first();
            if ($delete && $table === 'KONTROL_INTERNAL_SOP' && DB::table('RISIKO_KONTROL_SOP')->where('kontrol_internal_id', $id)->where('sop_versi_id', $targetId)->exists()) {
                throw new BusinessRuleException('tautan_masih_dirujuk', 'Tautan SOP masih menjadi acuan risiko.');
            }
            if ($delete) {
                if (! $old) {
                    abort(404);
                } try {
                    DB::table($table)->where($keys)->delete();
                } catch (QueryException $exception) {
                    if (str_starts_with((string) $exception->getCode(), '23')) {
                        throw new BusinessRuleException('tautan_masih_dirujuk', 'Tautan masih dirujuk data lain.');
                    } throw $exception;
                }
            } else {
                if ($old) {
                    throw new BusinessRuleException('tautan_sudah_ada', 'Tautan sudah ada.');
                } DB::table($table)->insert([...$keys, ...array_intersect_key($data, array_flip($fields)), 'created_at' => now(), 'updated_at' => now()]);
            }
            app(AuditService::class)->record($table, $id, $delete ? 'DELETE' : 'CREATE', $old ? (array) $old : null, $delete ? null : $data, $actor->id);

            return $data;
        });
    }
}
