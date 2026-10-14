<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\AsesmenRisiko;
use App\Models\KeputusanRisiko;
use App\Models\MatriksRisiko;
use App\Models\PenilaianRisiko;
use App\Models\Risiko;
use App\Models\User;
use App\Models\UsulanKeputusan;

class KeputusanService
{
    public function decide(AsesmenRisiko $assessment, User $actor, string $note): void
    {
        foreach (Risiko::where('asesmen_id', $assessment->id)->get() as $risk) {
            $current = PenilaianRisiko::where('risiko_id', $risk->id)->where('tahap', 'SAAT_INI')->latest('id')->firstOrFail();
            $proposal = UsulanKeputusan::where('penilaian_id', $current->id)->latest('id')->firstOrFail();
            $rating = $proposal->keputusan === 'MITIGASI' ? PenilaianRisiko::where('risiko_id', $risk->id)->where('tahap', 'AKTUAL_SETELAH')->latest('id')->first() : $current;
            if (! $rating) {
                throw new BusinessRuleException('residual_belum_lengkap', 'Penilaian keputusan belum tersedia.');
            }
            KeputusanRisiko::create(['penilaian_id' => $rating->id, 'diputuskan_oleh' => $actor->id, 'keputusan' => $proposal->keputusan, 'alasan' => $note, 'diputuskan_pada' => now()]);
        }
    }

    public function residualAchieved(Risiko $risk): ?bool
    {
        $target = PenilaianRisiko::where('risiko_id', $risk->id)->where('tahap', 'TARGET_SETELAH')->latest('id')->first();
        $actual = PenilaianRisiko::where('risiko_id', $risk->id)->where('tahap', 'AKTUAL_SETELAH')->latest('id')->first();
        if (! $target || ! $actual) {
            return null;
        }
        $targetCell = MatriksRisiko::findOrFail($target->matriks_id);
        $actualCell = MatriksRisiko::findOrFail($actual->matriks_id);
        $rank = fn (MatriksRisiko $cell): int => (int) MatriksRisiko::where('versi_metode_id', $cell->versi_metode_id)->where('tingkat_risiko_id', $cell->tingkat_risiko_id)->min('skor');

        return $rank($actualCell) <= $rank($targetCell);
    }
}
