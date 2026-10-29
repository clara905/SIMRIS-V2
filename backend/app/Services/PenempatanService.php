<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\UnitOrganisasi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

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
        $mulai = Carbon::parse($values['mulai'])->startOfDay();
        $selesai = isset($values['selesai']) ? Carbon::parse($values['selesai'])->startOfDay() : null;
        $placements = PenempatanPegawai::where('pegawai_id', $pegawai->id)
            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
            ->lockForUpdate()
            ->get();

        if (! $record && $values['utama'] && $selesai === null) {
            foreach ($placements->where('utama', true)->whereNull('selesai') as $previous) {
                $previousStart = Carbon::parse($previous->mulai)->startOfDay();
                if ($mulai->lte($previousStart)) {
                    throw new BusinessRuleException('mulai_penempatan_tidak_valid', 'Penempatan baru harus mulai setelah penempatan utama sebelumnya.');
                }
                $previous->selesai = $mulai->copy()->subDay()->toDateString();
                if ($this->overlaps($previous, $placements->where('id', '!=', $previous->id))) {
                    throw new BusinessRuleException('penempatan_tumpang_tindih', 'Periode penempatan pegawai tidak boleh tumpang tindih. Akhiri penempatan lama sebelum membuat penempatan baru.');
                }
                $previous->save();
            }
        }

        $candidate = $record ?? new PenempatanPegawai;
        $candidate->fill($values);
        if ($this->overlaps($candidate, $placements)) {
            throw new BusinessRuleException('penempatan_tumpang_tindih', 'Periode penempatan pegawai tidak boleh tumpang tindih. Akhiri penempatan lama sebelum membuat penempatan baru.');
        }

        $record ??= $candidate;
        $record->fill($data)->save();

        return $record->refresh();
    }

    /** @param Collection<int, PenempatanPegawai> $others */
    private function overlaps(PenempatanPegawai $candidate, Collection $others): bool
    {
        $start = Carbon::parse($candidate->mulai)->startOfDay();
        $end = $candidate->selesai ? Carbon::parse($candidate->selesai)->startOfDay() : null;

        foreach ($others as $other) {
            $otherStart = Carbon::parse($other->mulai)->startOfDay();
            $otherEnd = $other->selesai ? Carbon::parse($other->selesai)->startOfDay() : null;
            if (($end === null || $otherStart->lte($end)) && ($otherEnd === null || $otherEnd->gte($start))) {
                return true;
            }
        }

        return false;
    }
}
