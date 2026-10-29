<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Aset;
use App\Models\Pegawai;
use App\Models\PenugasanAset;
use App\Models\UnitOrganisasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AsetService
{
    public const LOCAL = ['unit_pemilik_id', 'kategori_id', 'klasifikasi_informasi_id', 'lokasi', 'deskripsi'];

    public function __construct(private ScopeUnit $scope, private KatalogService $catalog) {}

    public function query(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('aset.view');

        $units = $this->units($actor);

        return Aset::whereIn('unit_pemilik_id', $units);
    }

    public function assignments(User $actor): Builder
    {
        return PenugasanAset::whereIn('aset_id', $this->query($actor)->select('id'));
    }

    public function listing(User $actor, array $input, bool $assignment = false): mixed
    {
        $query = $assignment ? $this->assignments($actor) : $this->query($actor);
        $allowed = $assignment ? ['id', 'aset_id', 'pegawai_id', 'jenis_tanggung_jawab', 'mulai', 'selesai'] : ['id', 'sumber_id', 'kategori_id', 'unit_pemilik_id', 'kondisi', 'status', 'perlu_ditinjau', 'tahun_pengadaan', 'nama', 'kode_aset', 'klasifikasi_informasi_id'];
        $filters = $input['filter'] ?? [];
        if (! $assignment && isset($filters['perlu_ditinjau']) && is_scalar($filters['perlu_ditinjau']) && in_array(strtolower((string) $filters['perlu_ditinjau']), ['1', 'true', 'belum_dinilai'], true)) {
            $query->where(fn (Builder $query) => $query->where('perlu_ditinjau', true)->orWhereNull('perlu_ditinjau'));
            unset($filters['perlu_ditinjau']);
            $input['filter'] = $filters;
        }
        if (! $assignment && isset($input['q'])) {
            $q = $input['q'];
            $query->where(function (Builder $query) use ($q): void {
                foreach (['nama', 'merk', 'nomor_seri', 'nomor_inventaris'] as $column) {
                    $query->orWhere($column, 'like', '%'.$q.'%');
                }
            });
        }

        return $this->catalog->filtered($query, $input, $allowed)->paginate((int) ($input['per_page'] ?? 20))->withQueryString();
    }

    public function update(int $id, array $data, User $actor, bool $assigned = false): Aset
    {
        return DB::transaction(function () use ($id, $data, $actor, $assigned): Aset {
            DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
            $asset = $this->query($actor)->whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->refresh())->authorize('aset.write', [(int) $asset->unit_pemilik_id]);
            if (isset($data['unit_pemilik_id'])) {
                $unit = UnitOrganisasi::whereIn('id', $this->units($actor))->findOrFail($data['unit_pemilik_id']);
                if (! $unit->aktif) {
                    throw new BusinessRuleException('unit_tidak_aktif', 'Unit pemilik harus aktif.');
                }
            }
            if (array_diff(array_keys($data), self::LOCAL)) {
                throw new BusinessRuleException('kolom_sumber_terkunci', 'Kolom sumber tidak dapat diubah secara lokal.');
            }
            $asset->fill($data);
            if ($assigned) {
                $asset->perlu_ditinjau = false;
            } $asset->save();

            return $asset;
        });
    }

    public function assignment(?int $id, array $data, User $actor, bool $delete = false): PenugasanAset
    {
        return DB::transaction(function () use ($id, $data, $actor, $delete): PenugasanAset {
            DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
            $model = $id ? $this->assignments($actor)->whereKey($id)->lockForUpdate()->firstOrFail() : new PenugasanAset;
            if ($id) {
                $old = $this->query($actor)->findOrFail($model->aset_id);
                Gate::forUser($actor)->authorize('aset.write', [(int) $old->unit_pemilik_id]);
            }
            if ($delete) {
                $model->delete();

                return $model;
            }
            $model->fill($data);
            $asset = $this->query($actor)->findOrFail($model->aset_id);
            Gate::forUser($actor)->authorize('aset.write', [(int) $asset->unit_pemilik_id]);
            $pegawai = Pegawai::whereIn('id', DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')->whereIn('unit_id', $this->units($actor))->where('mulai', '<=', today())->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today())))->findOrFail($model->pegawai_id);
            if (! $pegawai->aktif) {
                throw new BusinessRuleException('pegawai_tidak_aktif', 'Pegawai penugasan harus aktif.');
            }
            if ($model->selesai && $model->selesai->lt($model->mulai)) {
                throw new BusinessRuleException('rentang_tidak_valid', 'Selesai tidak boleh sebelum mulai.');
            }
            $overlap = PenugasanAset::where('aset_id', $model->aset_id)
                ->where('jenis_tanggung_jawab', $model->jenis_tanggung_jawab)
                ->when($id, fn ($q) => $q->where('id', '!=', $id))
                ->where(fn ($q) => $q->whereNull('selesai')->orWhereDate('selesai', '>=', $model->mulai->toDateString()))
                ->when($model->selesai, fn ($q) => $q->whereDate('mulai', '<=', $model->selesai->toDateString()))
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw new BusinessRuleException('penugasan_tumpang_tindih', 'Periode penugasan aset untuk jenis tanggung jawab yang sama tidak boleh tumpang tindih.');
            }
            $model->save();

            return $model;
        });
    }

    /** Wilayah admin satker selalu berasal dari role ADMIN_SATKER aktif. */
    private function units(User $actor): array
    {
        return $this->scope->isSuper($actor)
            ? $this->scope->ids($actor)
            : ($this->scope->hasRole($actor, ['ADMIN_SATKER'])
                ? $this->scope->idsForRoles($actor, ['ADMIN_SATKER'])
                : $this->scope->ids($actor));
    }
}
