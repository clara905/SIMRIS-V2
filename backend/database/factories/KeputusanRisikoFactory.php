<?php

namespace Database\Factories;

use App\Models\AsesmenRisiko;
use App\Models\KeputusanRisiko;
use App\Models\PenilaianRisiko;
use App\Models\Risiko;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KeputusanRisiko> */
class KeputusanRisikoFactory extends Factory
{
    protected $model = KeputusanRisiko::class;

    public function definition(): array
    {
        return ['penilaian_id' => function (): int {
            $rating = PenilaianRisiko::factory()->create();
            $assessment = AsesmenRisiko::findOrFail(Risiko::findOrFail($rating->risiko_id)->asesmen_id);
            $assessment->workflowMutation = true;
            $assessment->update(['status' => 'MENUNGGU_KASATKER', 'dibekukan_awal_pada' => now()]);

            return $rating->id;
        }, 'diputuskan_oleh' => User::factory(), 'keputusan' => 'TERIMA', 'alasan' => 'Alasan keputusan', 'diputuskan_pada' => now()];
    }
}
