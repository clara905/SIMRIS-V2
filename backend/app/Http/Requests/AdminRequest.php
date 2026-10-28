<?php

namespace App\Http\Requests;

use App\Services\AdminService;
use App\Services\ScopeUnit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $resource = $this->route('resource');
        $class = AdminService::MODELS[$resource];
        $service = app(AdminService::class);
        if ($this->route('id')) {
            $model = $service->query($resource, $this->user())->findOrFail($this->route('id'));
            if (! $this->isMethod('GET')) {
                Gate::authorize($this->isMethod('DELETE') ? 'admin.delete' : 'admin.update', [$model]);
            }
        } else {
            Gate::authorize($this->isMethod('GET') ? 'admin.view' : 'admin.create', [$class]);
        }

        return true;
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'nip.unique' => 'NIP sudah terdaftar. Hubungi super admin untuk proses mutasi.',
        ];
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'sort' => ['sometimes', 'string'], 'filter' => ['sometimes', 'array']];
        }
        $resource = $this->route('resource');
        $action = $this->route('action');
        if ($action === 'reset-password') {
            return ['password' => ['required', 'string', 'min:12', 'max:255', 'confirmed']];
        }
        if ($action === 'pindah-induk') {
            return ['parent_id' => ['present', 'nullable', 'integer', Rule::exists('UNIT_ORGANISASI', 'id')]];
        }
        if ($action || $this->isMethod('DELETE')) {
            return [];
        }
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $id = $this->route('id');
        $unique = fn (string $table, string $column) => Rule::unique($table, $column)->ignore($id);
        $actor = $this->user();
        $satkerAdmin = $actor !== null && app(ScopeUnit::class)->hasRole($actor, ['ADMIN_SATKER']) && ! app(ScopeUnit::class)->isSuper($actor);

        return match ($resource) {
            'users' => ['pegawai_id' => [$satkerAdmin && $this->isMethod('POST') ? 'required' : 'sometimes', 'nullable', 'integer', Rule::exists('PEGAWAI', 'id'), $unique('USERS', 'pegawai_id')], 'username' => [$required, 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', $unique('USERS', 'username')], 'email_login' => [$required, 'email', 'max:255', $unique('USERS', 'email_login')], 'password' => $this->isMethod('POST') ? ['required', 'string', 'min:12', 'max:255', 'confirmed'] : ['prohibited'], 'aktif' => ['sometimes', 'boolean'], 'wajib_ganti_password' => ['prohibited'], 'password_hash' => ['prohibited']],
            'user-role-scope' => ['user_id' => [$required, 'integer', Rule::exists('USERS', 'id')], 'role_id' => [$required, 'integer', Rule::exists('ROLES', 'id')], 'unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('UNIT_ORGANISASI', 'id')], 'mulai' => [$required, 'date'], 'selesai' => ['sometimes', 'nullable', 'date']],
            'unit-organisasi' => ['parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('UNIT_ORGANISASI', 'id')], 'kode' => [$required, 'string', 'max:255', $unique('UNIT_ORGANISASI', 'kode')], 'nama' => [$required, 'string', 'max:255'], 'jenis' => [$required, Rule::in(array_keys(config('aturan_unit')))], 'aktif' => ['sometimes', 'boolean']],
            'pegawai' => ['nip' => ['sometimes', 'nullable', 'regex:/^[0-9]{18}$/', $unique('PEGAWAI', 'nip')], 'nama' => [$required, 'string', 'max:255'], 'email_kontak' => [$required, 'email', 'max:255'], 'jenis_kepegawaian' => [$required, 'string', 'max:255'], 'aktif' => ['sometimes', 'boolean']],
            'penempatan-pegawai' => ['pegawai_id' => [$required, 'integer', Rule::exists('PEGAWAI', 'id')], 'unit_id' => [$required, 'integer', Rule::exists('UNIT_ORGANISASI', 'id')], 'mulai' => [$required, 'date_format:Y-m-d'], 'selesai' => ['sometimes', 'nullable', 'date_format:Y-m-d'], 'utama' => ['sometimes', 'boolean']],
            'periode-penilaian' => ['nama' => [$required, 'string', 'max:255'], 'tanggal_mulai' => [$required, 'date_format:Y-m-d'], 'tanggal_selesai' => [$required, 'date_format:Y-m-d']],
            'rencana-kerja' => ['kode' => [$required, 'string', 'max:255', $unique('RENCANA_KERJA', 'kode')], 'uraian_template' => [$required, 'string', 'max:10000']],
            default => [],
        };
    }
}
