<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Aset;
use App\Models\AsesmenAset;
use App\Models\KategoriAset;
use App\Models\Pegawai;
use App\Models\PenugasanAset;
use App\Models\SumberAset;
use App\Models\UnitOrganisasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AsetService
{
    public const MANUAL_SOURCE_CODE = 'INPUT_MANUAL';

    public const LOCAL = ['unit_pemilik_id', 'kategori_id', 'klasifikasi_informasi_id', 'lokasi', 'deskripsi'];

    private const MANAGED_FIELDS = ['unit_pemilik_id', 'kategori_id', 'klasifikasi_informasi_id', 'kode_aset', 'nama', 'deskripsi', 'merk', 'nomor_seri', 'jumlah', 'kondisi', 'lokasi', 'tanggal_pengadaan', 'status'];

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
        if (isset($filters['sumber'])) {
            if ($filters['sumber'] !== 'lokal') {
                throw ValidationException::withMessages(['filter.sumber' => ['Filter sumber aset tidak diperbolehkan.']]);
            }
            $query->whereIn('sumber_id', SumberAset::where('kode', self::MANUAL_SOURCE_CODE)->select('id'));
            unset($filters['sumber']);
            $input['filter'] = $filters;
        }
        if (! $assignment && isset($filters['perlu_ditinjau']) && is_scalar($filters['perlu_ditinjau']) && in_array(strtolower((string) $filters['perlu_ditinjau']), ['1', 'true', 'belum_dinilai'], true)) {
            $query->where(fn (Builder $query) => $query->where('perlu_ditinjau', true)->orWhereNull('perlu_ditinjau'));
            unset($filters['perlu_ditinjau']);
            $input['filter'] = $filters;
        }
        return $this->catalog->filtered($query, $input, $allowed)->paginate((int) ($input['per_page'] ?? 20))->withQueryString();
    }

    public function options(User $actor): array
    {
        $units = UnitOrganisasi::whereIn('id', $this->units($actor))
            ->where('aktif', true)
            ->orderBy('kode')
            ->get(['id', 'parent_id', 'kode', 'nama']);
        $categories = KategoriAset::orderBy('kode')->get(['id', 'parent_id', 'kode', 'nama']);

        return ['units' => $units, 'categories' => $categories];
    }

    public function create(array $data, User $actor): Aset
    {
        return DB::transaction(function () use ($data, $actor): Aset {
            DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
            $units = UnitOrganisasi::whereIn('id', $this->units($actor))->where('aktif', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $unitId = isset($data['unit_pemilik_id']) ? (int) $data['unit_pemilik_id'] : null;
            if ($unitId === null && count($units) === 1) {
                $unitId = $units[0];
            }
            if ($unitId === null || ! in_array($unitId, $units, true)) {
                throw ValidationException::withMessages(['unit_pemilik_id' => ['Pilih unit aktif dalam lingkup Kasub.']]);
            }

            $values = array_intersect_key($data, array_flip(self::MANAGED_FIELDS));
            $values['unit_pemilik_id'] = $unitId;
            $providedCode = trim((string) ($values['kode_aset'] ?? ''));
            $generateCode = $providedCode === '';
            $values['kode_aset'] = $generateCode ? 'AUTO-'.Str::upper((string) Str::ulid()) : $providedCode;
            $values['deskripsi'] ??= '';
            $values['merk'] = filled($values['merk'] ?? null) ? trim((string) $values['merk']) : null;
            $values['nomor_seri'] = filled($values['nomor_seri'] ?? null) ? trim((string) $values['nomor_seri']) : null;
            $values['jumlah'] ??= 1;
            $values['tanggal_pengadaan'] = $values['tanggal_pengadaan'] ?? null;
            $values['klasifikasi_informasi_id'] = $values['klasifikasi_informasi_id'] ?? null;
            $values['sumber_id'] = $this->manualSourceId();
            $values['external_id'] = (string) Str::uuid();
            $values['last_synced_at'] = now();
            $values['data_mentah'] = [];
            $values['hash_data'] = hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $values['perlu_ditinjau'] = true;

            $asset = Aset::create($values);
            if ($generateCode) {
                $index = (int) $asset->id;
                while (Aset::where('kode_aset', (string) $index)->exists()) {
                    $index++;
                }
                $asset->kode_aset = (string) $index;
                $asset->save();
            }
            Gate::forUser($actor)->authorize('aset.write', [$unitId]);

            return $asset;
        });
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
            $allowed = $this->isManual($asset) ? self::MANAGED_FIELDS : self::LOCAL;
            if (array_diff(array_keys($data), $allowed)) {
                throw new BusinessRuleException('kolom_sumber_terkunci', 'Kolom sumber tidak dapat diubah secara lokal.');
            }
            if (array_key_exists('deskripsi', $data) && $data['deskripsi'] === null) {
                $data['deskripsi'] = '';
            }
            $asset->fill($data);
            if ($assigned) {
                $asset->perlu_ditinjau = false;
            } $asset->save();

            return $asset;
        });
    }

    public function delete(int $id, User $actor): void
    {
        DB::transaction(function () use ($id, $actor): void {
            DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
            $asset = $this->query($actor)->whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->refresh())->authorize('aset.write', [(int) $asset->unit_pemilik_id]);
            if (! $this->isManual($asset)) {
                throw new BusinessRuleException('aset_sumber_terkunci', 'Aset hasil sinkronisasi tidak dapat dihapus dari inventaris lokal.');
            }
            if (PenugasanAset::where('aset_id', $asset->id)->exists() || AsesmenAset::where('aset_id', $asset->id)->exists()) {
                throw new BusinessRuleException('aset_sedang_digunakan', 'Aset sudah digunakan dalam penugasan atau asesmen sehingga tidak dapat dihapus.');
            }
            $asset->delete();
        });
    }

    public function isManual(Aset $asset): bool
    {
        return SumberAset::whereKey($asset->sumber_id)->where('kode', self::MANUAL_SOURCE_CODE)->exists();
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
                : ($this->scope->hasRole($actor, ['KASUB'])
                    ? $this->scope->idsForRoles($actor, ['KASUB'])
                    : $this->scope->ids($actor)));
    }

    private function manualSourceId(): int
    {
        DB::table('SUMBER_ASET')->insertOrIgnore([
            'kode' => self::MANUAL_SOURCE_CODE,
            'nama' => 'Inventaris lokal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('SUMBER_ASET')->where('kode', self::MANUAL_SOURCE_CODE)->value('id');
    }
}
