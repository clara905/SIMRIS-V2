<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\PeriodePenilaian;
use App\Models\RencanaKerja;
use App\Models\Role;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UserRoleScope;
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

    public const FILTERS = ['users' => ['id', 'username', 'email_login', 'aktif', 'pegawai_id'], 'user-role-scope' => ['id', 'user_id', 'role_id', 'unit_id'], 'roles' => ['id', 'kode', 'nama'], 'unit-organisasi' => ['id', 'kode', 'nama', 'jenis', 'parent_id', 'aktif'], 'pegawai' => ['id', 'nip', 'nama', 'aktif', 'jenis_kepegawaian'], 'penempatan-pegawai' => ['id', 'pegawai_id', 'unit_id', 'utama', 'mulai', 'selesai'], 'periode-penilaian' => ['id', 'nama', 'tanggal_mulai', 'tanggal_selesai'], 'rencana-kerja' => ['id', 'kode', 'uraian_template']];

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

        return match ($resource) {
            'unit-organisasi' => $query->whereIn('id', $ids)->whereIn('id', $lead ? $ids : DB::table('PENEMPATAN_PEGAWAI')->where('pegawai_id', $user->pegawai_id)->where('utama', true)->whereNull('selesai')->where('mulai', '<=', today())->pluck('unit_id')),
            'pegawai' => $lead ? $query->whereIn('id', DB::table('PENEMPATAN_PEGAWAI')->select('pegawai_id')->whereIn('unit_id', $ids)->where('utama', true)->where('mulai', '<=', today())->whereNull('selesai')) : $query->whereKey($user->pegawai_id ?? 0),
            'penempatan-pegawai' => $lead ? $query->whereIn('unit_id', $ids) : $query->where('pegawai_id', $user->pegawai_id ?? 0)->whereIn('unit_id', $ids),
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

        return $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id')->paginate((int) ($input['per_page'] ?? 20))->withQueryString();
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
                if ($action === 'delete' && in_array($resource, ['users', 'unit-organisasi', 'pegawai'], true)) {
                    $data = ['aktif' => false];
                    $action = 'update';
                }
                if ($resource === 'users') {
                    if (! $this->scope->isSuper($actor)) {
                        $this->assertSatkerUserTarget($model, $data, $actor);
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
                if ($resource === 'unit-organisasi' && $action !== 'delete') {
                    if (! $this->scope->isSuper($actor)) {
                        $this->assertSatkerUnit($model, $data, $action, $actor);
                    }
                    $this->hierarki->validateUnit($data, $id ? $model : null);
                }
                if ($resource === 'pegawai' && ! $this->scope->isSuper($actor) && $id && ! $this->pegawaiInSatkerScope((int) $model->id, $actor)) {
                    throw new BusinessRuleException('pegawai_di_luar_scope', 'Pegawai harus memiliki penempatan aktif di wilayah Admin Satker.');
                }
                if ($resource === 'penempatan-pegawai' && ! $this->scope->isSuper($actor) && isset($data['unit_id']) && ! in_array((int) $data['unit_id'], $this->scope->idsForRoles($actor, ['ADMIN_SATKER']), true)) {
                    throw new BusinessRuleException('unit_di_luar_scope', 'Penempatan hanya boleh berada di wilayah Admin Satker.');
                }
                if ($resource === 'periode-penilaian') {
                    $values = [...$before, ...$data];
                    if (isset($values['tanggal_mulai'], $values['tanggal_selesai']) && Carbon::parse($values['tanggal_selesai'])->lt(Carbon::parse($values['tanggal_mulai']))) {
                        throw new BusinessRuleException('rentang_tanggal_tidak_valid', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
                    }
                }
                if ($action === 'delete') {
                    $model->delete();
                } elseif ($resource === 'penempatan-pegawai') {
                    $model = $this->penempatan->save($data, $id ? $model : null);
                } else {
                    $model->fill($data)->save();
                }
                if ($resource === 'unit-organisasi' && ! $id && $model->jenis === 'SATKER' && ! $this->scope->isSuper($actor)) {
                    $adminSatkerRole = Role::where('kode', 'ADMIN_SATKER')->firstOrFail();
                    $scope = UserRoleScope::create([
                        'user_id' => $actor->id,
                        'role_id' => $adminSatkerRole->id,
                        'unit_id' => $model->id,
                        'mulai' => now(),
                        'selesai' => null,
                    ]);
                    $this->audit->record(
                        $scope->getTable(),
                        (int) $scope->id,
                        'ROLE_CHANGE',
                        null,
                        [...$scope->getAttributes(), 'oleh_admin_satker' => true, 'role' => 'ADMIN_SATKER'],
                    );
                }
                if (in_array($resource, ['users', 'user-role-scope'], true)) {
                    $this->assertSuperAdminRemains();
                }
                if ($resource === 'user-role-scope') {
                    $after = $action === 'delete' ? null : $model->getAttributes();
                    if (! $this->scope->isSuper($actor)) {
                        $roleCode = $action === 'delete' ? Role::find($before['role_id'])?->kode : Role::find($model->role_id)?->kode;
                        $marker = ['oleh_admin_satker' => true, 'role' => $roleCode];
                        $before = [...$before, ...$marker];
                        $after = $after === null ? null : [...$after, ...$marker];
                    }
                    $this->audit->record($model->getTable(), (int) $model->id, 'ROLE_CHANGE', $before, $after);
                }
                if ($action === 'reset-password') {
                    $this->audit->record('USERS', (int) $model->id, 'PASSWORD_RESET');
                }
                $this->scope->forget($actor);

                return $model;
            });
        } catch (QueryException $exception) {
            if (str_starts_with((string) $exception->getCode(), '23') || in_array($exception->errorInfo[1] ?? null, [19, 1062, 1451, 1452], true)) {
                throw new BusinessRuleException('integritas_data', 'Data masih dirujuk atau melanggar kunci unik. Periksa kembali data terkait.');
            }
            throw $exception;
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
        if ($model->exists && ! in_array((int) $model->id, $units, true)) {
            throw new BusinessRuleException('unit_di_luar_scope', 'Unit berada di luar wilayah Admin Satker.');
        }
        $parentWasChanged = array_key_exists('parent_id', $data) || ! $model->exists;
        $parentId = $data['parent_id'] ?? ($model->exists ? $model->parent_id : null);
        $creatingSatkerRoot = ! $model->exists && $action === 'create' && ($data['jenis'] ?? null) === 'SATKER' && $parentId === null;
        if ($parentWasChanged && ! $creatingSatkerRoot && ($parentId === null || ! in_array((int) $parentId, $units, true))) {
            throw new BusinessRuleException('induk_di_luar_scope', 'Unit baru atau perpindahan unit harus memiliki induk di wilayah Admin Satker.');
        }
        $active = $data['aktif'] ?? ($action === 'nonaktifkan' ? false : true);
        $adminRoots = DB::table('USER_ROLE_SCOPE as s')->join('ROLES as r', 'r.id', '=', 's.role_id')->where('s.user_id', $actor->id)->where('r.kode', 'ADMIN_SATKER')->whereNotNull('s.unit_id')->where('s.mulai', '<=', now())->where(fn ($q) => $q->whereNull('s.selesai')->orWhere('s.selesai', '>=', now()))->pluck('s.unit_id')->map(fn ($id) => (int) $id)->all();
        if ($model->exists && ! $active && ($model->parent_id === null || in_array((int) $model->id, $adminRoots, true))) {
            throw new BusinessRuleException('unit_induk_tidak_dapat_dinonaktifkan', 'Unit induk wilayah Admin Satker tidak dapat dinonaktifkan oleh ADMIN_SATKER.');
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
