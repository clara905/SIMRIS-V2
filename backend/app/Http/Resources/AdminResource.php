<?php

namespace App\Http\Resources;

use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\PeriodePenilaian;
use App\Models\RencanaKerja;
use App\Models\Role;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\ScopeUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/** @property User|Role|UserRoleScope|UnitOrganisasi|Pegawai|PenempatanPegawai|PeriodePenilaian|RencanaKerja $resource */
class AdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        if ($request->user() && app(ScopeUnit::class)->hasRole($request->user(), ['ADMIN_SATKER']) && ! app(ScopeUnit::class)->isSuper($request->user())) {
            $protected = match (true) {
                $this->resource instanceof User => DB::table('USER_ROLE_SCOPE as s')->join('ROLES as r', 'r.id', '=', 's.role_id')->where('s.user_id', $this->resource->id)->whereIn('r.kode', ['ADMIN_SATKER', 'SUPER_ADMIN'])->where('s.mulai', '<=', now())->where(fn ($q) => $q->whereNull('s.selesai')->orWhere('s.selesai', '>=', now()))->exists(),
                $this->resource instanceof UserRoleScope => in_array(Role::find($this->resource->role_id)?->kode, ['ADMIN_SATKER', 'SUPER_ADMIN'], true),
                default => false,
            };
            $data['dapat_diubah'] = ! $protected;
            if ($protected) {
                $data['alasan_tidak_dapat_diubah'] = 'Akun atau role privileged hanya dapat dikelola oleh SUPER_ADMIN.';
            }
        }

        return $data;
    }
}
