<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\UnitOrganisasi;
use Carbon\Carbon;

class PenempatanService
{
    public function save(array $data, ?PenempatanPegawai $record = null): PenempatanPegawai
    {
        $values = [...($record?->getAttributes() ?? ['utama' => true, 'selesai' => null]), ...$data];
        if ($record && $record->pegawai_id !== (int) $values['pegawai_id']) {
            throw new BusinessRuleException('pegawai_penempatan_tetap', 'Pegawai pada riwayat penempatan tidak dapat diganti.');
        }
        $pegawai = Pegawai::whereKey($values['pegawai_id'])->lockForUpdate()->firstOrFail();
        $unit = UnitOrganisasi::findOrFail($values['unit_id']);
        if (! $pegawai->aktif || ! $unit->aktif) {
            throw new BusinessRuleException('penempatan_tidak_aktif', 'Pegawai dan unit tujuan harus aktif.');
        }
        if ($values['selesai'] && Carbon::parse($values['selesai'])->lt(Carbon::parse($values['mulai']))) {
            throw new BusinessRuleException('rentang_tanggal_tidak_valid', 'Tanggal selesai tidak boleh sebelum mulai.');
        }
        if ($values['utama'] && ! $values['selesai']) {
            $previous = PenempatanPegawai::where('pegawai_id', $pegawai->id)->where('utama', true)->whereNull('selesai')->when($record, fn ($q) => $q->where('id', '!=', $record->id))->lockForUpdate()->first();
            if ($previous) {
                if (Carbon::parse($values['mulai'])->lte($previous->mulai)) {
                    throw new BusinessRuleException('mulai_penempatan_tidak_valid', 'Penempatan baru harus mulai setelah penempatan utama sebelumnya.');
                }
                $previous->update(['selesai' => Carbon::parse($values['mulai'])->subDay()->toDateString()]);
            }
        }
        $record ??= new PenempatanPegawai;
        $record->fill($data)->save();

        return $record->refresh();
    }
}
