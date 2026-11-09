<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\MatriksRisiko;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\TingkatRisiko;
use App\Models\VersiMetodeRisiko;
use Illuminate\Validation\ValidationException;

class HitungRisiko
{
    public static function skor(int $kemungkinan, int $dampak): int
    {
        return $kemungkinan * $dampak;
    }

    /** @return array{matriks: MatriksRisiko, skor: int, tingkat: TingkatRisiko, perlu_rtp: ?bool, perlu_persetujuan_pemilik: ?bool, kriteria_penerimaan: ?string} */
    public function hitung(VersiMetodeRisiko $method, int $likelihood, int $impact): array
    {
        if ($method->profil_penilaian && (! in_array($likelihood, range(1, 5), true) || ! in_array($impact, range(1, 5), true))) {
            throw ValidationException::withMessages([
                'kemungkinan_nilai' => ['Kemungkinan harus berupa bilangan bulat 1 sampai 5.'],
                'dampak_nilai' => ['Dampak harus berupa bilangan bulat 1 sampai 5.'],
            ]);
        }

        $possibility = SkalaKemungkinan::where('versi_metode_id', $method->id)->where('nilai', $likelihood)->first();
        $severity = SkalaDampak::where('versi_metode_id', $method->id)->where('nilai', $impact)->first();
        if (! $possibility || ! $severity) {
            throw new BusinessRuleException('skala_metode_tidak_lengkap', 'Nilai kemungkinan dan dampak tidak tersedia pada versi metode asesmen.');
        }
        $cell = MatriksRisiko::where('versi_metode_id', $method->id)
            ->where('kemungkinan_id', $possibility->id)
            ->where('dampak_id', $severity->id)
            ->first();
        if (! $cell) {
            throw new BusinessRuleException('sel_matriks_tidak_ditemukan', 'Pasangan kemungkinan dan dampak belum tersedia pada matriks metode.');
        }

        return $this->fromCell($method, $cell, $possibility, $severity, TingkatRisiko::findOrFail($cell->tingkat_risiko_id));
    }

    /** @return array{matriks: MatriksRisiko, skor: int, tingkat: TingkatRisiko, perlu_rtp: ?bool, perlu_persetujuan_pemilik: ?bool, kriteria_penerimaan: ?string} */
    public function fromCell(
        VersiMetodeRisiko $method,
        MatriksRisiko $cell,
        SkalaKemungkinan $possibility,
        SkalaDampak $impact,
        TingkatRisiko $level,
    ): array {
        if ((int) $cell->versi_metode_id !== (int) $method->id
            || (int) $possibility->versi_metode_id !== (int) $method->id
            || (int) $impact->versi_metode_id !== (int) $method->id
            || (int) $level->versi_metode_id !== (int) $method->id) {
            throw new BusinessRuleException('matriks_beda_versi', 'Matriks, skala, dan tingkat risiko harus berasal dari metode asesmen.');
        }
        $score = (int) $cell->skor;
        if ($method->profil_penilaian && ($level->batas_min_skor === null || $level->batas_max_skor === null || $score < $level->batas_min_skor || $score > $level->batas_max_skor)) {
            throw new BusinessRuleException('tingkat_tidak_sesuai_skor', 'Tingkat risiko tidak sesuai batas skor pada profil metode.');
        }

        return [
            'matriks' => $cell,
            'skor' => $score,
            'tingkat' => $level,
            'perlu_rtp' => $method->profil_penilaian ? $level->perlu_rtp : null,
            'perlu_persetujuan_pemilik' => $method->profil_penilaian ? $level->perlu_persetujuan_pemilik : null,
            'kriteria_penerimaan' => $method->profil_penilaian ? $level->kriteria_penerimaan : null,
        ];
    }
}
