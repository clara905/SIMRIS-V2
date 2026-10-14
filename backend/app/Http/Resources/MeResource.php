<?php

namespace App\Http\Resources;

use App\Models\Pegawai;
use App\Models\User;
use App\Services\ScopeUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/** @mixin User */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => $this->resource->only(['id', 'pegawai_id', 'username', 'email_login', 'aktif', 'wajib_ganti_password']),
            'pegawai' => $this->pegawai_id ? Pegawai::find($this->pegawai_id) : null,
            'roles' => app(ScopeUnit::class)->roles($this->resource)->map(fn ($role) => ['id' => $role->id, 'role_id' => $role->role_id, 'kode' => $role->kode, 'nama' => $role->nama, 'unit_id' => $role->unit_id, 'mulai' => Carbon::parse($role->mulai)->toIso8601String(), 'selesai' => $role->selesai ? Carbon::parse($role->selesai)->toIso8601String() : null]),
            'jalur_unit' => $this->pegawai_id ? DB::table('v_pegawai_unit')->where('pegawai_id', $this->pegawai_id)->first() : null,
        ];
    }
}
