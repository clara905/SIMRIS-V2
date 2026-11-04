<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Pegawai;
use App\Services\ScopeUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pegawai = $this->pegawai_id ? Pegawai::find($this->pegawai_id) : null;
        if ($pegawai) {
            $hasNip = $pegawai->nip !== null && $pegawai->nip !== '';
            $pegawai->setAttribute('nip_tersedia', $hasNip);
            $pegawai->setAttribute('nip', $hasNip ? str_repeat('•', 18) : null);
        }

        return [
            'user' => $this->resource->only(['id', 'pegawai_id', 'username', 'email_login', 'aktif', 'wajib_ganti_password']),
            'pegawai' => $pegawai,
            'roles' => app(ScopeUnit::class)->roles($this->resource)->map(fn ($role) => ['id' => $role->id, 'role_id' => $role->role_id, 'kode' => $role->kode, 'nama' => $role->nama, 'unit_id' => $role->unit_id, 'mulai' => Carbon::parse($role->mulai)->toIso8601String(), 'selesai' => $role->selesai ? Carbon::parse($role->selesai)->toIso8601String() : null]),
            'jalur_unit' => $this->pegawai_id ? DB::table('v_pegawai_unit')->where('pegawai_id', $this->pegawai_id)->first() : null,
            'unit_id' => $this->pegawai_id ? DB::table('PENEMPATAN_PEGAWAI')->where('pegawai_id', $this->pegawai_id)->where('utama', true)->where('mulai', '<=', today())->where(fn ($q) => $q->whereNull('selesai')->orWhere('selesai', '>=', today()))->value('unit_id') : null,
        ];
    }
}
