<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\PenilaianRisiko;
use App\Models\MatriksRisiko;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\User;
use App\Models\UsulanKeputusan;
use App\Models\VersiMetodeRisiko;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PenilaianService
{
    public function __construct(private AsesmenService $assessments) {}

    public function assess(int $riskId, array $data, User $actor): PenilaianRisiko
    {
        return DB::transaction(function () use ($riskId, $data, $actor): PenilaianRisiko {
            $risk = $this->assessments->risks($actor)->findOrFail($riskId);
            if ($data['tahap'] === 'AKTUAL_SETELAH') {
                $assessment = $this->assessments->assessments($actor)->whereKey($risk->asesmen_id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('asesmen.write', [$assessment]);
                if (! in_array($assessment->status, ['DIREVIU', 'RESIDUAL_DRAFT'], true) || $assessment->dibekukan_pada || ! in_array($risk->id, app(PenangananService::class)->mitigationIds($assessment), true)) {
                    throw new BusinessRuleException('residual_bukan_draft', 'Penilaian aktual hanya dapat diisi untuk risiko MODIFIKASI pada asesmen DIREVIU.');
                }

                $matrix = $this->resolveMatrix($assessment->versi_metode_id, $data);
                $this->assertLowerScore($risk->id, $matrix, 'Penilaian aktual harus lebih rendah daripada risiko SAAT_INI.');
                $data['matriks_id'] = $matrix->id;
                unset($data['kemungkinan_nilai'], $data['dampak_nilai']);

                return PenilaianRisiko::create([...$data, 'risiko_id' => $risk->id, 'dinilai_oleh' => $actor->id, 'dinilai_pada' => now()]);
            }
            $assessment = $this->assessments->editable($risk->asesmen_id, $actor);
            $method = VersiMetodeRisiko::findOrFail($assessment->versi_metode_id);
            $matrix = $this->resolveMatrix($method->id, $data);
            if ($data['tahap'] === 'TARGET_SETELAH') {
                $this->assertLowerScore($risk->id, $matrix, 'Skor target setelah penanganan harus lebih rendah daripada skor SAAT_INI.');
            }
            $data['matriks_id'] = $matrix->id;
            unset($data['kemungkinan_nilai'], $data['dampak_nilai']);
            $rating = PenilaianRisiko::firstOrNew(['risiko_id' => $risk->id, 'tahap' => $data['tahap']]);
            $rating->fill([...$data, 'dinilai_oleh' => $actor->id, 'dinilai_pada' => now()])->save();

            return $rating;
        });
    }

    public function propose(int $riskId, array $data, User $actor): UsulanKeputusan
    {
        return DB::transaction(function () use ($riskId, $data, $actor): UsulanKeputusan {
            $risk = $this->assessments->risks($actor)->findOrFail($riskId);
            $this->assessments->editable($risk->asesmen_id, $actor);
            $rating = PenilaianRisiko::where('risiko_id', $risk->id)->where('tahap', 'SAAT_INI')->latest('id')->first();
            if (! $rating) {
                throw new BusinessRuleException('penilaian_belum_ada', 'Penilaian SAAT_INI diperlukan sebelum usulan.');
            }
            $assessment = $this->assessments->assessments($actor)->findOrFail($risk->asesmen_id);
            $decision = $data['keputusan'] === 'MITIGASI' ? 'MODIFIKASI' : $data['keputusan'];
            if ($decision === 'MODIFIKASI') {
                $target = PenilaianRisiko::where('risiko_id', $risk->id)->where('tahap', 'TARGET_SETELAH')->latest('id')->first();
                if (! $target) {
                    throw new BusinessRuleException('target_wajib', 'Risiko MODIFIKASI harus memiliki penilaian TARGET_SETELAH.');
                }
                $targetCell = MatriksRisiko::findOrFail($target->matriks_id);
                $currentCell = MatriksRisiko::findOrFail($rating->matriks_id);
                if ((int) $targetCell->skor >= (int) $currentCell->skor) {
                    throw new BusinessRuleException('target_tidak_turun', 'Skor target setelah penanganan harus lebih rendah daripada skor SAAT_INI.');
                }
            }
            $proposal = UsulanKeputusan::firstOrNew(['penilaian_id' => $rating->id]);
            $proposal->fill([...$data, 'keputusan' => $decision, 'diusulkan_oleh' => $actor->id, 'diusulkan_pada' => now()])->save();

            return $proposal;
        });
    }

    private function resolveMatrix(int $methodId, array &$data): MatriksRisiko
    {
        if (isset($data['kemungkinan_nilai'], $data['dampak_nilai'])) {
            $possibility = SkalaKemungkinan::where('versi_metode_id', $methodId)->where('nilai', $data['kemungkinan_nilai'])->first();
            $impact = SkalaDampak::where('versi_metode_id', $methodId)->where('nilai', $data['dampak_nilai'])->first();
            if (! $possibility || ! $impact) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'kemungkinan_nilai' => ['Nilai skala tidak tersedia pada metode asesmen.'],
                    'dampak_nilai' => ['Nilai skala tidak tersedia pada metode asesmen.'],
                ]);
            }
            $matrix = MatriksRisiko::where('versi_metode_id', $methodId)
                ->where('kemungkinan_id', $possibility->id)
                ->where('dampak_id', $impact->id)
                ->first();
            if (! $matrix) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'kemungkinan_nilai' => ['Pasangan kemungkinan dan dampak tidak tersedia pada matriks metode asesmen.'],
                    'dampak_nilai' => ['Pasangan kemungkinan dan dampak tidak tersedia pada matriks metode asesmen.'],
                ]);
            }

            return $matrix;
        }

        $matrix = MatriksRisiko::findOrFail((int) $data['matriks_id']);
        if ((int) $matrix->versi_metode_id !== $methodId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'matriks_id' => ['Matriks harus berasal dari versi metode yang terikat pada asesmen.'],
            ]);
        }

        return $matrix;
    }

    private function assertLowerScore(int $riskId, MatriksRisiko $candidate, string $message): void
    {
        $current = PenilaianRisiko::where('risiko_id', $riskId)->where('tahap', 'SAAT_INI')->latest('id')->first();
        if (! $current) {
            throw new BusinessRuleException('penilaian_saat_ini_diperlukan', 'Penilaian SAAT_INI harus disimpan terlebih dahulu.');
        }
        $currentCell = MatriksRisiko::findOrFail($current->matriks_id);
        if ((int) $candidate->skor >= (int) $currentCell->skor) {
            throw new BusinessRuleException('skor_tidak_turun', $message);
        }
    }
}
