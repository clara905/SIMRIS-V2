<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class PenggunaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email_login,
            'nama' => $this->pegawai?->nama ?? $this->username,
            'aktif' => $this->aktif,
            'peran' => $this->penugasanAktif->map(fn ($p) => [
                'kode' => $p->role->kode,
                'nama' => $p->role->nama,
                'unit' => $p->unit === null ? null : [
                    'id' => $p->unit->id,
                    'kode' => $p->unit->kode,
                    'nama' => $p->unit->nama,
                    'jenis' => $p->unit->jenis,
                ],
            ])->values(),
        ];
    }
}