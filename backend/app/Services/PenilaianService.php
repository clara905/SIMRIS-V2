<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\PenilaianRisiko;
use App\Models\User;
use App\Models\UsulanKeputusan;
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
                if ($assessment->status !== 'RESIDUAL_DRAFT' || $assessment->dibekukan_pada || ! in_array($risk->id, app(PenangananService::class)->mitigationIds($assessment), true)) {
                    throw new BusinessRuleException('residual_bukan_draft', 'Penilaian aktual hanya untuk risiko MITIGASI pada RESIDUAL_DRAFT.');
                }

                return PenilaianRisiko::create([...$data, 'risiko_id' => $risk->id, 'dinilai_oleh' => $actor->id, 'dinilai_pada' => now()]);
            }
            $this->assessments->editable($risk->asesmen_id, $actor);
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
            $proposal = UsulanKeputusan::firstOrNew(['penilaian_id' => $rating->id]);
            $proposal->fill([...$data, 'diusulkan_oleh' => $actor->id, 'diusulkan_pada' => now()])->save();

            return $proposal;
        });
    }
}
