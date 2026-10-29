<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Kontrol;
use App\Models\KontrolInternal;
use App\Models\Pegawai;
use App\Models\Soa;
use App\Models\SoaBenchmark;
use App\Models\SoaDetail;
use App\Models\SoaReview;
use App\Models\SoaVersi;
use App\Models\Sop;
use App\Models\SopReview;
use App\Models\SopVersi;
use App\Models\UnitOrganisasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class KepatuhanService
{
    public const MODELS = ['kontrol-internal' => KontrolInternal::class, 'sop' => Sop::class, 'sop-versi' => SopVersi::class, 'sop-review' => SopReview::class, 'soa' => Soa::class, 'soa-versi' => SoaVersi::class, 'soa-detail' => SoaDetail::class, 'soa-benchmark' => SoaBenchmark::class, 'soa-review' => SoaReview::class];

    public const PARENTS = ['sop-versi' => ['sop', 'sop_id'], 'sop-review' => ['sop-versi', 'sop_versi_id'], 'soa-versi' => ['soa', 'soa_id'], 'soa-detail' => ['soa-versi', 'soa_versi_id'], 'soa-benchmark' => ['soa-detail', 'soa_detail_id'], 'soa-review' => ['soa-versi', 'soa_versi_id']];

    public function __construct(private ScopeUnit $scope, private DokumenService $documents) {}

    public function query(string $resource, User $actor): Builder
    {
        Gate::forUser($actor)->authorize('asesmen.view');
        $chain = [];
        while (isset(self::PARENTS[$resource])) {
            [$parent, $field] = self::PARENTS[$resource];
            $chain[] = [self::MODELS[$resource], $field];
            $resource = $parent;
        }
        $class = self::MODELS[$resource];
        $unitIds = $this->units($actor);
        $query = $class::whereIn($resource === 'soa' ? 'unit_id' : 'unit_pemilik_id', $unitIds);
        foreach (array_reverse($chain) as [$child, $field]) {
            $query = $child::whereIn($field, $query->select('id'));
        }

        return $query;
    }

    public function unit(Model $model): int
    {
        $resource = array_search($model::class, self::MODELS, true);
        while (isset(self::PARENTS[$resource])) {
            [$resource, $field] = self::PARENTS[$resource];
            $class = self::MODELS[$resource];
            $model = $class::findOrFail($model->$field);
        }

        return (int) ($model->unit_id ?? $model->unit_pemilik_id);
    }

    public function version(Model $model): ?Model
    {
        if ($model instanceof SopVersi || $model instanceof SoaVersi) {
            return $model;
        }
        if ($model instanceof SoaBenchmark) {
            $model = SoaDetail::findOrFail($model->soa_detail_id);
        }

        return $model instanceof SoaDetail ? SoaVersi::findOrFail($model->soa_versi_id) : null;
    }

    public function draft(Model $model): void
    {
        $version = $this->version($model);
        if ($version) {
            $version = $version->newQuery()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($version->status !== 'DRAFT') {
                throw new BusinessRuleException('versi_bukan_draft', 'Versi yang diajukan atau selesai tidak dapat diubah.');
            }
        }
    }

    public function mutate(string $resource, ?int $id, ?int $parentId, array $data, User $actor, bool $delete = false): Model
    {
        try {
            return DB::transaction(function () use ($resource, $id, $parentId, $data, $actor, $delete): Model {
                $class = self::MODELS[$resource];
                $model = $id ? $this->query($resource, $actor)->whereKey($id)->lockForUpdate()->firstOrFail() : new $class;
                if (! $id && isset(self::PARENTS[$resource])) {
                    [$parent,$field] = self::PARENTS[$resource];
                    $parentModel = $this->query($parent, $actor)->whereKey($parentId)->lockForUpdate()->firstOrFail();
                    $model->$field = $parentId;
                    $unit = $this->unit($parentModel);
                } else {
                    $unit = $id ? $this->unit($model) : (int) ($data['unit_id'] ?? $data['unit_pemilik_id']);
                    UnitOrganisasi::whereIn('id', $this->units($actor))->findOrFail($unit);
                }
                Gate::forUser($actor)->authorize('kepatuhan.write', [$unit, $delete, $resource]);
                if ($id) {
                    $this->draft($model);
                }
                if ($delete) {
                    $model->delete();

                    return $model;
                }
                if ($model instanceof SopVersi && isset($data['dokumen_versi_id'])) {
                    $this->documents->finalVersion((int) $data['dokumen_versi_id'], $actor);
                }
                if (! $id && ($model instanceof SopVersi || $model instanceof SoaVersi)) {
                    $field = $model instanceof SopVersi ? 'sop_id' : 'soa_id';
                    $number = 1;
                    while ($class::where($field, $parentId)->where('nomor_versi', (string) $number)->exists()) {
                        $number++;
                    }
                    $model->fill(['dibuat_oleh' => $actor->id, 'nomor_versi' => (string) $number, 'status' => 'DRAFT', 'dibuat_pada' => now()]);
                }
                if ($model instanceof SoaDetail) {
                    $version = SoaVersi::findOrFail($model->soa_versi_id);
                    $this->draft($version);
                    $soa = Soa::findOrFail($version->soa_id);
                    Kontrol::where('standar_id', $soa->standar_id)->findOrFail($data['kontrol_id'] ?? $model->kontrol_id);
                    if (isset($data['penanggung_jawab_id'])) {
                        Pegawai::where('aktif', true)->whereIn('id', DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')->whereIn('unit_id', $this->units($actor))->where('mulai', '<=', today())->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today())))->findOrFail($data['penanggung_jawab_id']);
                    }
                }
                $model->fill($data)->save();

                return $model;
            });
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23')) {
                throw new BusinessRuleException('data_masih_dirujuk_atau_duplikat', 'Data masih dirujuk atau kombinasi kunci sudah digunakan.');
            } throw $exception;
        }
    }

    public function review(string $resource, int $id, string $action, array $data, User $actor): Model
    {
        return DB::transaction(function () use ($resource, $id, $action, $data, $actor): Model {
            DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
            $model = $this->query($resource, $actor)->whereKey($id)->lockForUpdate()->firstOrFail();
            $unit = $this->unit($model);
            $transition = config('alur_kepatuhan.'.$resource.'.'.$model->status.'.'.$action);
            if (! $transition) {
                throw new BusinessRuleException('transisi_tidak_valid', 'Transisi review versi tidak tersedia.');
            }
            $reviewClass = $resource === 'sop-versi' ? SopReview::class : SoaReview::class;
            $field = $resource === 'sop-versi' ? 'sop_versi_id' : 'soa_versi_id';
            if ($action === 'AJUKAN') {
                Gate::forUser($actor)->authorize('kepatuhan.write', [$unit, true]);
            } elseif ($resource === 'sop-versi') {
                Gate::forUser($actor)->authorize('kepatuhan.review-sop');
                if (($data['tahap'] ?? null) !== 'SUPER_ADMIN') {
                    throw new BusinessRuleException('peninjau_sop_harus_super_admin', 'Hanya SUPER_ADMIN yang dapat meninjau dan mengesahkan SOP.');
                }
                if ($actor->id === $model->dibuat_oleh) {
                    throw new BusinessRuleException('pemisahan_tugas', 'Pengaju SOP tidak dapat meninjau versinya sendiri.');
                }
            } else {
                app(ReviewScope::class)->authorize($actor, $unit, $transition['pelaku']);
                if (($data['tahap'] ?? null) !== $transition['pelaku']) {
                    throw new BusinessRuleException('transisi_tidak_valid', 'Tahap review tidak sesuai status.');
                }
                if ($actor->id === $model->dibuat_oleh || ($transition['pelaku'] !== 'KASUB' && $reviewClass::where($field, $id)->where('reviewer_id', $actor->id)->where('tahap', 'KASUB')->exists())) {
                    throw new BusinessRuleException('pemisahan_tugas', 'Penyusun tidak dapat mereview sendiri dan Kasub tidak merangkap reviewer berikutnya.');
                }
            }
            if ($action === 'TOLAK' && trim($data['catatan'] ?? '') === '') {
                throw new BusinessRuleException('catatan_wajib', 'Catatan penolakan wajib diisi.');
            }
            if ($action === 'SETUJU') {
                if ($model instanceof SopVersi) {
                    $this->documents->finalVersion($model->dokumen_versi_id, $actor);
                } else {
                    $details = SoaDetail::where('soa_versi_id', $id)->get();
                    if ($details->isEmpty()) {
                        throw new BusinessRuleException('soa_belum_lengkap', 'Minimal satu detail kontrol diperlukan.');
                    } foreach (DB::table('BUKTI_SOA')->whereIn('soa_detail_id', $details->pluck('id'))->get() as $proof) {
                        $this->documents->finalVersion((int) $proof->dokumen_versi_id, $actor);
                    }
                }
            }
            $next = $transition['ke'];
            if ($model instanceof SoaVersi) {
                $skip = config('alur_review.lewati.'.UnitOrganisasi::findOrFail($unit)->jenis, []);
                if ($next === 'MENUNGGU_KASUB' && in_array('KASUB', $skip, true)) {
                    $next = 'MENUNGGU_KABID';
                }
                if ($next === 'MENUNGGU_KABID' && in_array('KABID', $skip, true)) {
                    $next = 'MENUNGGU_KASATKER';
                }
            }
            if ($action !== 'AJUKAN') {
                $reviewClass::create([$field => $id, 'reviewer_id' => $actor->id, 'tahap' => $transition['pelaku'], 'keputusan' => $action, 'catatan' => $data['catatan'] ?? '', 'waktu' => now()]);
            }
            $before = $model->getAttributes();
            $model->workflowMutation = true;
            try {
                $model->status = $next;
                if ($model instanceof SoaVersi && in_array($next, ['DISAHKAN', 'DITOLAK'], true)) {
                    $model->dibekukan_pada = now();
                } $model->save();
            } finally {
                $model->workflowMutation = false;
            }
            app(AuditService::class)->record($model->getTable(), $id, $action === 'SETUJU' ? 'APPROVE' : ($action === 'TOLAK' ? 'REJECT' : 'UPDATE'), $before, $model->getAttributes(), $actor->id);

            return $model;
        });
    }

    /** Wilayah admin satker tidak mengambil cakupan dari role lain pada akun yang sama. */
    private function units(User $actor): array
    {
        return $this->scope->isSuper($actor)
            ? $this->scope->ids($actor)
            : ($this->scope->hasRole($actor, ['ADMIN_SATKER'])
                ? $this->scope->idsForRoles($actor, ['ADMIN_SATKER'])
                : $this->scope->ids($actor));
    }
}
