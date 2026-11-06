<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\PeriodePenilaian;
use App\Models\RencanaKerja;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UnitOrganisasi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminService
{
    public const MODELS = ['users' => User::class, 'user-role-scope' => UserRoleScope::class, 'roles' => Role::class, 'unit-organisasi' => UnitOrganisasi::class, 'pegawai' => Pegawai::class, 'penempatan-pegawai' => PenempatanPegawai::class, 'periode-penilaian' => PeriodePenilaian::class, 'rencana-kerja' => RencanaKerja::class];

    public const FILTERS = ['users' => ['id', 'username', 'email_login', 'aktif', 'pegawai_id'], 'user-role-scope' => ['id', 'user_id', 'role_id', 'unit_id'], 'roles' => ['id', 'kode', 'nama'], 'unit-organisasi' => ['id', 'kode', 'nama', 'jenis', 'parent_id', 'aktif'], 'pegawai' => ['id', 'nama', 'aktif', 'jenis_kepegawaian'], 'penempatan-pegawai' => ['id', 'pegawai_id', 'unit_id', 'utama', 'mulai', 'selesai'], 'periode-penilaian' => ['id', 'nama', 'tanggal_mulai', 'tanggal_selesai'], 'rencana-kerja' => ['id', 'kode', 'uraian_template']];

    public function __construct(private ScopeUnit $scope, private HierarkiService $hierarki, private PenempatanService $penempatan, private AuditService $audit) {}

    public function query(string $resource, User $user): Builder
    {
        $class = self::MODELS[$resource];
        Gate::authorize('admin.view', [$class]);
        $query = $class::query();
        if ($this->scope->isSuper($user)) {
            return $query;
        }
        $isSatkerAdmin = $this->scope->hasRole($user, ['ADMIN_SATKER']);
        $ids = $isSatkerAdmin ? $this->scope->idsForRoles($user, ['ADMIN_SATKER']) : $this->scope->ids($user);
        if ($isSatkerAdmin) {
            $placedEmployees = DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')->whereIn('unit_id', $ids)->where('mulai', '<=', today())->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()));

            return match ($resource) {
                'users' => $query->whereIn('pegawai_id', (clone $placedEmployees)->select('pegawai_id')),
                'user-role-scope' => $query->whereIn('user_id', User::whereIn('pegawai_id', (clone $placedEmployees)->select('pegawai_id'))->select('id')),
                'roles' => $query->whereIn('kode', ['STAFF', 'KASUB', 'KABID', 'KASATKER']),
                'unit-organisasi' => $query->whereIn('id', $ids),
                'pegawai' => $query->whereIn('id', (clone $placedEmployees)->select('pegawai_id')),
                'penempatan-pegawai' => $query->whereIn('unit_id', $ids),
                default => $query->whereRaw('1 = 0'),
            };
        }
        $lead = $this->scope->hasRole($user, ['ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB']);
        $isKabidOnly = $this->scope->hasRole($user, ['KABID'])
            && ! $this->scope->hasRole($user, ['ADMIN_SATKER', 'SUPER_ADMIN']);
        $kabidSubbidangIds = $isKabidOnly
            ? UnitOrganisasi::whereIn('id', $ids)->where('jenis', 'SUBBIDANG')->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        $kasubSubbidangIds = $this->scope->roles($user)
            ->where('kode', 'KASUB')
            ->pluck('unit_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->intersect($ids)
            ->values()
            ->all();

        return match ($resource) {
            'unit-organisasi' => $this->scope->hasRole($user, ['KASUB']) && ! $this->scope->hasRole($user, ['ADMIN_SATKER', 'SUPER_ADMIN'])
                ? $query->whereIn('id', $kasubSubbidangIds)->where('jenis', 'SUBBIDANG')
                : ($isKabidOnly
                    ? $query->whereIn('id', $ids)->where('jenis', 'SUBBIDANG')
                    : $query->whereIn('id', $ids)->whereIn('id', $lead ? $ids : DB::table('PENEMPATAN_PEGAWAI')->where('pegawai_id', $user->pegawai_id)->where('utama', true)->whereNull('selesai')->where('mulai', '<=', today())->pluck('unit_id'))),
            'pegawai' => $isKabidOnly
                ? $query->whereIn('id', DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')->whereIn('unit_id', $kabidSubbidangIds)->where('utama', true)->where('mulai', '<=', today())->whereNull('selesai'))
                : ($lead ? $query->whereIn('id', DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')->whereIn('unit_id', $ids)->where('utama', true)->where('mulai', '<=', today())->whereNull('selesai')) : $query->whereKey($user->pegawai_id ?? 0)),
            'penempatan-pegawai' => $isKabidOnly
                ? $query->whereIn('unit_id', $kabidSubbidangIds)
                : ($lead ? $query->whereIn('unit_id', $ids) : $query->where('pegawai_id', $user->pegawai_id ?? 0)->whereIn('unit_id', $ids)),
            default => $query,
        };
    }

    public function listing(string $resource, User $user, array $input): mixed
    {
        $query = $this->query($resource, $user);
        $allowed = self::FILTERS[$resource];
        foreach ($input['filter'] ?? [] as $field => $value) {
            if (! in_array($field, $allowed, true) || ! is_scalar($value)) {
                throw ValidationException::withMessages(['filter' => ['Kolom atau nilai filter tidak diperbolehkan.']]);
            }
            $query->where($field, $value);
        }
        $sort = $input['sort'] ?? 'id';
        $column = ltrim($sort, '-');
        if (! in_array($column, [...$allowed, 'created_at', 'updated_at'], true)) {
            throw ValidationException::withMessages(['sort' => ['Kolom urutan tidak diperbolehkan.']]);
        }

        $page = $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate((int) ($input['per_page'] ?? 20))
            ->withQueryString();
        if ($resource === 'users' && $page->isNotEmpty()) {
            $employeeIds = $page->getCollection()->pluck('pegawai_id')->filter()->unique()->values();
            $placements = DB::table('PENEMPATAN_PEGAWAI as p')
                ->join('UNIT_ORGANISASI as u', 'u.id', '=', 'p.unit_id')
                ->whereIn('p.pegawai_id', $employeeIds)
                ->where('p.mulai', '<=', today())
                ->where(fn ($q) => $q->whereNull('p.selesai')->orWhere('p.selesai', '>=', today()))
                ->orderByDesc('p.utama')
                ->orderBy('u.kode')
                ->get(['p.pegawai_id', 'p.unit_id', 'p.mulai', 'p.selesai', 'p.utama', 'u.kode', 'u.nama', 'u.jenis'])
                ->groupBy('pegawai_id');
            foreach ($page->getCollection() as $user) {
                $user->setAttribute(
                    'penempatan_aktif',
                    $placements->get($user->pegawai_id, collect())->map(fn ($placement) => [
                        'unit_id' => $placement->unit_id,
                        'kode' => $placement->kode,
                        'nama' => $placement->nama,
                        'jenis' => $placement->jenis,
                        'utama' => (bool) $placement->utama,
                        'mulai' => $placement->mulai,
                        'selesai' => $placement->selesai,
                    ])->values()->all(),
                );
            }
        }

        return $page;
    }

    public function mutate(string $resource, ?int $id, array $data, string $action, User $actor): Model
    {
        try {
            return DB::transaction(function () use ($resource, $id, $data, $action, $actor): Model {
                DB::table('ROLES')->where('kode', 'SUPER_ADMIN')->lockForUpdate()->first();
                $actor->refresh();
                $class = self::MODELS[$resource];
                $model = $id ? $this->query($resource, $actor)->whereKey($id)->lockForUpdate()->firstOrFail() : new $class;
                Gate::authorize($id ? ($action === 'delete' ? 'admin.delete' : 'admin.update') : 'admin.create', [$id ? $model : $class]);
                $before = $model->getAttributes();
                if (in_array($action, ['aktifkan', 'nonaktifkan'], true)) {
                    $data = ['aktif' => $action === 'aktifkan'];
                }
                if ($resource === 'users') {
                    if (! $this->scope->isSuper($actor)) {
                        $this->assertSatkerUserTarget($model, $data, $actor);
                    }
                    if ($action === 'delete' && (int) $model->id === (int) $actor->id) {
                        throw new BusinessRuleException('tidak_boleh_hapus_diri', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
                    }
                    if ($model->id === $actor->id && array_key_exists('aktif', $data) && ! $data['aktif']) {
                        throw new BusinessRuleException('tidak_boleh_nonaktifkan_diri', 'SUPER_ADMIN tidak boleh menonaktifkan akun sendiri.');
                    }
                    if (isset($data['password'])) {
                        $data['password_hash'] = $data['password'];
                        unset($data['password']);
                        $data['wajib_ganti_password'] = true;
                    }
                    if (! $id) {
                        $data['wajib_ganti_password'] = true;
                    }
                }
                if ($resource === 'user-role-scope') {
                    $data = $this->validateScope($model, $data, $action, $actor);
                }
                if ($resource === 'unit-organisasi') {
                    if (! $this->scope->isSuper($actor)) {
                        $this->assertSatkerUnit($model, $data, $action, $actor);
                    }
                    if ($action === 'delete') {
                        $this->hierarki->validateUnitDelete($model);
                    } else {
                        $this->hierarki->validateUnit($data, $id ? $model : null);
                    }
                }
                if ($resource === 'pegawai' && ! $this->scope->isSuper($actor) && $id && ! $this->pegawaiInSatkerScope((int) $model->id, $actor)) {
                    throw new BusinessRuleException('pegawai_di_luar_scope', 'Pegawai harus memiliki penempatan aktif di wilayah Admin Satker.');
                }
                if ($resource === 'penempatan-pegawai' && ! $this->scope->isSuper($actor)) {
                    $satkerUnits = $this->scope->idsForRoles($actor, ['ADMIN_SATKER']);
                    $units = UnitOrganisasi::query()->get(['id', 'parent_id']);
                    $manageableUnitIds = $satkerUnits;
                    do {
                        $previousCount = count($manageableUnitIds);
                        foreach ($units as $unit) {
                            if ($unit->parent_id !== null && in_array((int) $unit->parent_id, $manageableUnitIds, true) && ! in_array((int) $unit->id, $manageableUnitIds, true)) {
                                $manageableUnitIds[] = (int) $unit->id;
                            }
                        }
                    } while ($previousCount !== count($manageableUnitIds));
                    $targetUnitId = isset($data['unit_id']) ? (int) $data['unit_id'] : ($model->exists ? (int) $model->unit_id : null);
                    if ($targetUnitId === null || ! in_array($targetUnitId, $manageableUnitIds, true)) {
                        throw new BusinessRuleException('unit_di_luar_scope', 'Penempatan hanya boleh berada di wilayah Admin Satker.');
                    }
                    if ($model->exists && ! in_array((int) $model->unit_id, $manageableUnitIds, true)) {
                        throw new BusinessRuleException('unit_di_luar_scope', 'Penempatan yang diubah harus berada di wilayah Admin Satker.');
                    }
                    $targetPegawaiId = (int) ($data['pegawai_id'] ?? $model->pegawai_id);
                    $activePlacementInOtherSatker = DB::table('PENEMPATAN_PEGAWAI')
                        ->where('pegawai_id', $targetPegawaiId)
                        ->where('utama', true)
                        ->where('mulai', '<=', today())
                        ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()))
                        ->exists();
                    if ($activePlacementInOtherSatker && ! DB::table('PENEMPATAN_PEGAWAI')
                        ->where('pegawai_id', $targetPegawaiId)
                        ->whereIn('unit_id', $satkerUnits)
                        ->where('utama', true)
                        ->where('mulai', '<=', today())
                        ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()))
                        ->exists()) {
                        throw new BusinessRuleException('mutasi_lintas_satker_dilarang', 'Mutasi pegawai antar satker hanya dapat dikerjakan oleh Super Admin.');
                    }
                    $activePlacementOutside = DB::table('PENEMPATAN_PEGAWAI')
                        ->where('pegawai_id', $targetPegawaiId)
                        ->when($model->exists, fn ($q) => $q->where('id', '!=', $model->id))
                        ->whereNotIn('unit_id', $satkerUnits)
                        ->where('mulai', '<=', today())
                        ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()))
                        ->exists();
                    if ($activePlacementOutside) {
                        throw new BusinessRuleException('mutasi_lintas_satker_dilarang', 'Mutasi pegawai antar satker hanya dapat dikerjakan oleh Super Admin.');
                    }
                }
                if ($resource === 'periode-penilaian') {
                    $values = [...$before, ...$data];
                    if (isset($values['tanggal_mulai'], $values['tanggal_selesai']) && Carbon::parse($values['tanggal_selesai'])->lt(Carbon::parse($values['tanggal_mulai']))) {
                        throw new BusinessRuleException('rentang_tanggal_tidak_valid', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
                    }
                }
                $roleScopeAudit = $resource === 'user-role-scope';
                if ($roleScopeAudit) {
                    $model->suppressAutomaticAudit = true;
                }
                try {
                    if ($action === 'delete') {
                        if ($resource === 'users') {
                            $this->deleteUserRoleScopes($model, $actor);
                        } elseif ($resource === 'pegawai') {
                            if (User::where('pegawai_id', $model->id)->exists()) {
                                throw new BusinessRuleException('pegawai_memiliki_akun', 'Hapus akun pengguna yang terhubung terlebih dahulu sebelum menghapus pegawai.');
                            }
                            $this->deleteEmployeePlacements($model, $actor);
                        } elseif ($resource === 'unit-organisasi') {
                            $this->deleteUnitRelations($model, $this->scope->isSuper($actor));
                        }
                        $model->delete();
                    } elseif ($resource === 'penempatan-pegawai') {
                        if (! $this->scope->isSuper($actor)) {
                            // Kolom implementasi utama tidak dikendalikan klien Admin Satker.
                            $data['utama'] = true;
                        }
                        $model = $this->penempatan->save($data, $id ? $model : null);
                    } else {
                        $model->fill($data)->save();
                    }
                } finally {
                    if ($roleScopeAudit) {
                        $model->suppressAutomaticAudit = false;
                    }
                }
                if (in_array($resource, ['users', 'user-role-scope'], true)) {
                    $this->assertSuperAdminRemains();
                }
                if ($resource === 'user-role-scope') {
                    $after = $action === 'delete' ? null : $model->getAttributes();
                    if (! $this->scope->isSuper($actor)) {
                        $roleCode = $action === 'delete' ? Role::find($before['role_id'])?->kode : Role::find($model->role_id)?->kode;
                        $marker = ['oleh_admin_satker' => true, 'peran' => $roleCode];
                        if ($roleCode === 'KASATKER') {
                            $marker['penanda'] = $action === 'delete' ? 'KASATKER_DICABUT' : 'KASATKER_DIBERIKAN';
                        }
                        $before = [...$before, ...$marker];
                        $after = [...($after ?? []), ...$marker];
                    } else {
                        $roleCode = $action === 'delete' ? Role::find($before['role_id'])?->kode : Role::find($model->role_id)?->kode;
                        $after = [...($after ?? []), 'oleh_admin_satker' => false, 'peran' => $roleCode];
                    }
                    $this->audit->record($model->getTable(), (int) $model->id, 'ROLE_CHANGE', $before, $after);
                }
                if ($action === 'reset-password') {
                    $this->audit->record('USERS', (int) $model->id, 'PASSWORD_RESET', null, ['oleh_admin_satker' => ! $this->scope->isSuper($actor)]);
                }
                $this->scope->forget($actor);

                return $model;
            });
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23') || in_array($exception->errorInfo[1] ?? null, [19, 1062, 1451, 1452], true)) {
                if ($action === 'delete' && $resource === 'pegawai') {
                    throw new BusinessRuleException('pegawai_masih_digunakan', 'Pegawai masih memiliki riwayat penempatan atau terhubung ke data operasional. Data terkait dipertahankan; hapus tidak dapat dilanjutkan.');
                }
                if ($action === 'delete' && $resource === 'users') {
                    throw new BusinessRuleException('pengguna_masih_digunakan', 'Akun masih dirujuk oleh audit atau riwayat operasional. Data terkait dipertahankan; hapus tidak dapat dilanjutkan.');
                }
                if ($action === 'delete' && $resource === 'unit-organisasi') {
                    throw new BusinessRuleException('unit_masih_digunakan', 'Unit masih dirujuk oleh data operasional lain (aset, dokumen, role, atau asesmen). Pindahkan atau hapus data terkait terlebih dahulu.');
                }
                throw new BusinessRuleException('integritas_data', 'Data masih dirujuk atau melanggar kunci unik. Periksa kembali data terkait.');
            }
            throw $exception;
        }
    }

    private function deleteUserRoleScopes(Model $user, User $actor): void
    {
        $query = UserRoleScope::where('user_id', $user->id);
        $superAdmin = $this->scope->isSuper($actor);
        if (! $superAdmin) {
            $unitIds = $this->scope->idsForRoles($actor, ['ADMIN_SATKER']);
            if ((clone $query)->where(fn ($scope) => $scope->whereNull('unit_id')->orWhereNotIn('unit_id', $unitIds))->exists()) {
                throw new BusinessRuleException('user_di_luar_scope', 'Akun memiliki role di luar wilayah Admin Satker dan tidak dapat dihapus.');
            }
        }
        $this->deleteRoleScopes($query, $superAdmin);
    }

    private function deleteEmployeePlacements(Model $pegawai, User $actor): void
    {
        $placements = PenempatanPegawai::where('pegawai_id', $pegawai->id)->lockForUpdate()->get();
        if (! $this->scope->isSuper($actor)) {
            $satkerUnits = $this->scope->idsForRoles($actor, ['ADMIN_SATKER']);
            if ($placements->contains(fn (PenempatanPegawai $placement): bool => ! in_array((int) $placement->unit_id, $satkerUnits, true))) {
                throw new BusinessRuleException('pegawai_di_luar_scope', 'Riwayat penempatan pegawai berada di luar wilayah Admin Satker dan tidak dapat dihapus.');
            }
        }
        foreach ($placements as $placement) {
            $placement->delete();
        }
    }

    private function deleteUnitRelations(Model $unit, bool $superAdmin): void
    {
        $placements = PenempatanPegawai::where('unit_id', $unit->id)->lockForUpdate()->get();
        foreach ($placements as $placement) {
            $placement->delete();
        }
        $this->deleteRoleScopes(UserRoleScope::where('unit_id', $unit->id), $superAdmin);
    }

    private function deleteRoleScopes(Builder $query, bool $superAdmin): void
    {
        $scopes = $query->lockForUpdate()->get();
        foreach ($scopes as $scope) {
            $roleCode = Role::find($scope->role_id)?->kode;
            $before = $scope->getAttributes();
            $marker = ['oleh_admin_satker' => ! $superAdmin, 'peran' => $roleCode];
            if ($roleCode === 'KASATKER') {
                $marker['penanda'] = 'KASATKER_DICABUT';
            }
            $scope->suppressAutomaticAudit = true;
            try {
                $scope->delete();
            } finally {
                $scope->suppressAutomaticAudit = false;
            }
            $this->audit->record(
                $scope->getTable(),
                (int) $scope->id,
                'ROLE_CHANGE',
                [...$before, ...$marker],
                $marker,
            );
        }
    }

    private function validateScope(Model $record, array $data, string $action, User $actor): array
    {
        $values = [...$record->getAttributes(), ...$data];
        $role = Role::findOrFail($values['role_id']);
        $unit = $values['unit_id'] ?? null;
        if (! $this->scope->isSuper($actor)) {
            $allowedRoles = ['STAFF', 'KASUB', 'KABID', 'KASATKER'];
            if (! in_array($role->kode, $allowedRoles, true)) {
                throw new BusinessRuleException('role_tidak_diizinkan', 'ADMIN_SATKER hanya dapat memberikan atau mencabut role STAFF, KASUB, KABID, dan KASATKER.');
            }
            if ($record->exists && in_array(Role::find($record->role_id)?->kode, ['ADMIN_SATKER', 'SUPER_ADMIN'], true)) {
                throw new BusinessRuleException('role_dilindungi', 'Role ADMIN_SATKER dan SUPER_ADMIN hanya dapat diubah oleh SUPER_ADMIN.');
            }
            if ($unit === null || ! in_array((int) $unit, $this->scope->idsForRoles($actor, ['ADMIN_SATKER']), true)) {
                throw new BusinessRuleException('unit_di_luar_scope', 'Role hanya dapat diberikan pada unit dalam wilayah Admin Satker.');
            }
            $targetUserId = (int) $values['user_id'];
            if (! User::whereKey($targetUserId)->whereIn('pegawai_id', DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')->whereIn('unit_id', $this->scope->idsForRoles($actor, ['ADMIN_SATKER']))->where('mulai', '<=', today())->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today())))->exists()) {
                throw new BusinessRuleException('user_di_luar_scope', 'Pengguna harus terhubung dengan pegawai yang memiliki penempatan aktif di wilayah Admin Satker.');
            }
            if ($this->hasActivePrivilegedRole($targetUserId)) {
                throw new BusinessRuleException('user_dilindungi', 'Akun ADMIN_SATKER atau SUPER_ADMIN hanya dapat dikelola oleh SUPER_ADMIN.');
            }
        }
        if (($role->kode === 'SUPER_ADMIN') !== ($unit === null)) {
            throw new BusinessRuleException('scope_unit_tidak_valid', 'Hanya SUPER_ADMIN yang wajib menggunakan scope global; role lain wajib memiliki unit.');
        }
        if ($role->kode === 'ADMIN_SATKER') {
            $targetUnit = $unit ? UnitOrganisasi::find($unit) : null;
            if (! $targetUnit || $targetUnit->jenis !== 'SATKER' || $targetUnit->parent_id !== null) {
                throw new BusinessRuleException('admin_satker_harus_satker_akar', 'Role ADMIN_SATKER wajib dipasang pada unit berjenis SATKER tingkat akar.');
            }
        }
        $start = Carbon::parse($values['mulai'])->setTimezone('Asia/Jakarta');
        $end = isset($values['selesai']) ? Carbon::parse($values['selesai'])->setTimezone('Asia/Jakarta') : null;
        if ($end && $end->lt($start)) {
            throw new BusinessRuleException('rentang_scope_tidak_valid', 'Selesai scope tidak boleh sebelum mulai.');
        }
        $oldSuper = $record->exists && Role::find($record->role_id)?->kode === 'SUPER_ADMIN';
        $revokes = $action === 'delete' || $role->kode !== 'SUPER_ADMIN' || (int) $values['user_id'] !== $actor->id || $start->isFuture() || ($end && $end->isPast());
        if ($oldSuper && $record->user_id === $actor->id && $revokes) {
            throw new BusinessRuleException('tidak_boleh_cabut_role_sendiri', 'SUPER_ADMIN tidak boleh mencabut role sendiri.');
        }
        if ($action !== 'delete') {
            $overlap = UserRoleScope::where('user_id', $values['user_id'])->where('role_id', $role->id)->where('unit_id', $unit)->when($record->exists, fn ($q) => $q->where('id', '!=', $record->id))->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', $start))->when($end, fn ($q) => $q->where('mulai', '<=', $end))->exists();
            if ($overlap) {
                throw new BusinessRuleException('scope_tumpang_tindih', 'Rentang scope dengan role dan unit yang sama bertumpang tindih.');
            }
            if ($role->kode === 'ADMIN_SATKER') {
                $satkerAdminOverlap = UserRoleScope::where('role_id', $role->id)
                    ->where('unit_id', $unit)
                    ->when($record->exists, fn ($q) => $q->where('id', '!=', $record->id))
                    ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', $start))
                    ->when($end, fn ($q) => $q->where('mulai', '<=', $end))
                    ->exists();
                if ($satkerAdminOverlap) {
                    throw new BusinessRuleException('admin_satker_ganda', 'Satker sudah memiliki Admin Satker aktif.');
                }
                $accountOtherSatkerOverlap = UserRoleScope::where('user_id', $values['user_id'])
                    ->where('role_id', $role->id)
                    ->where('unit_id', '!=', $unit)
                    ->when($record->exists, fn ($q) => $q->where('id', '!=', $record->id))
                    ->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', $start))
                    ->when($end, fn ($q) => $q->where('mulai', '<=', $end))
                    ->exists();
                if ($accountOtherSatkerOverlap) {
                    throw new BusinessRuleException('admin_satker_satu_satker', 'Satu akun hanya boleh memegang role ADMIN_SATKER aktif pada satu satker.');
                }
            }
        }
        if (array_key_exists('mulai', $data)) {
            $data['mulai'] = $start->format('Y-m-d H:i:s');
        }
        if (array_key_exists('selesai', $data)) {
            $data['selesai'] = $end?->format('Y-m-d H:i:s');
        }

        return $data;
    }

    /** Aturan tambahan untuk menjaga akun administratif di dalam scope ADMIN_SATKER. */
    private function assertSatkerUserTarget(User $model, array $data, User $actor): void
    {
        if ($model->exists && $model->id === $actor->id) {
            throw new BusinessRuleException('akun_sendiri_dilindungi', 'ADMIN_SATKER tidak dapat mengubah, menonaktifkan, atau mereset password akunnya sendiri melalui administrasi.');
        }
        if ($model->exists && $this->hasActivePrivilegedRole((int) $model->id)) {
            throw new BusinessRuleException('user_dilindungi', 'Akun ADMIN_SATKER atau SUPER_ADMIN hanya dapat dikelola oleh SUPER_ADMIN.');
        }
        $pegawaiId = $model->exists ? ($data['pegawai_id'] ?? $model->pegawai_id) : ($data['pegawai_id'] ?? null);
        if (! $pegawaiId || ! $this->pegawaiInSatkerScope((int) $pegawaiId, $actor)) {
            throw new BusinessRuleException('user_di_luar_scope', 'Pengguna harus terhubung dengan pegawai yang memiliki penempatan aktif di wilayah Admin Satker.');
        }
        if (isset($data['pegawai_id']) && $model->exists && (int) $data['pegawai_id'] !== (int) $model->pegawai_id) {
            throw new BusinessRuleException('pegawai_user_tetap', 'Pegawai yang terhubung pada akun tidak dapat diganti oleh ADMIN_SATKER.');
        }
    }

    private function assertSatkerUnit(Model $model, array $data, string $action, User $actor): void
    {
        $units = $this->scope->idsForRoles($actor, ['ADMIN_SATKER']);
        if ($model->exists) {
            if (! in_array((int) $model->id, $units, true)) {
                throw new BusinessRuleException('unit_di_luar_scope', 'Unit berada di luar wilayah Admin Satker.');
            }
            if ($model->parent_id === null || $model->jenis === 'SATKER') {
                $allowedFields = ['kode', 'nama'];
                if ($action === 'delete' || array_diff(array_keys($data), $allowedFields)) {
                    throw new BusinessRuleException('unit_satker_dilindungi', 'Admin Satker hanya dapat mengubah kode dan nama SATKER yang menjadi wilayahnya.');
                }

                return;
            }
        }
        if (! $model->exists) {
            $jenis = $data['jenis'] ?? null;
            if ($jenis === 'SATKER') {
                throw new BusinessRuleException('induk_di_luar_scope', 'Admin Satker tidak boleh membuat unit berjenis SATKER.');
            }
        }
        $parentWasChanged = array_key_exists('parent_id', $data) || ! $model->exists;
        $parentId = $data['parent_id'] ?? ($model->exists ? $model->parent_id : null);
        if ($parentWasChanged && ($parentId === null || ! in_array((int) $parentId, $units, true))) {
            throw new BusinessRuleException('induk_di_luar_scope', 'Unit baru atau perpindahan unit harus memiliki induk di wilayah Admin Satker.');
        }
    }

    private function pegawaiInSatkerScope(int $pegawaiId, User $actor): bool
    {
        return DB::table('PENEMPATAN_PEGAWAI')->where('pegawai_id', $pegawaiId)->whereIn('unit_id', $this->scope->idsForRoles($actor, ['ADMIN_SATKER']))->where('mulai', '<=', today())->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()))->exists();
    }

    private function hasActivePrivilegedRole(int $userId): bool
    {
        return DB::table('USER_ROLE_SCOPE as s')->join('ROLES as r', 'r.id', '=', 's.role_id')->where('s.user_id', $userId)->whereIn('r.kode', ['ADMIN_SATKER', 'SUPER_ADMIN'])->where('s.mulai', '<=', now())->where(fn ($q) => $q->whereNull('s.selesai')->orWhere('s.selesai', '>=', now()))->exists();
    }

    private function assertSuperAdminRemains(): void
    {
        $exists = DB::table('USER_ROLE_SCOPE as s')->join('USERS as u', 'u.id', '=', 's.user_id')->join('ROLES as r', 'r.id', '=', 's.role_id')->where('r.kode', 'SUPER_ADMIN')->whereNull('s.unit_id')->where('u.aktif', true)->where('s.mulai', '<=', now())->where(fn ($q) => $q->whereNull('s.selesai')->orWhere('s.selesai', '>=', now()))->exists();
        if (! $exists) {
            throw new BusinessRuleException('super_admin_terakhir', 'Minimal satu SUPER_ADMIN aktif harus tetap tersedia.');
        }
    }
}
