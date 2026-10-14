<?php

namespace Database\Factories;

use App\Models\AreaDampak;
use App\Models\KriteriaDampak;
use App\Models\MatriksRisiko;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\TingkatRisiko;
use App\Models\VersiMetodeRisiko;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VersiMetodeRisiko> */
class VersiMetodeRisikoFactory extends Factory
{
    protected $model = VersiMetodeRisiko::class;

    public function frozen(): static
    {
        return $this->afterCreating(function (VersiMetodeRisiko $version): void {
            $possibility = SkalaKemungkinan::factory()->create(['versi_metode_id' => $version->id]);
            $impact = SkalaDampak::factory()->create(['versi_metode_id' => $version->id]);
            $level = TingkatRisiko::factory()->create(['versi_metode_id' => $version->id]);
            foreach (AreaDampak::all() as $area) {
                KriteriaDampak::factory()->create(['skala_dampak_id' => $impact->id, 'area_dampak_id' => $area->id]);
            }
            MatriksRisiko::factory()->create(['versi_metode_id' => $version->id, 'kemungkinan_id' => $possibility->id, 'dampak_id' => $impact->id, 'tingkat_risiko_id' => $level->id]);
            $version->update(['dibekukan' => true]);
        });
    }

    public function definition(): array
    {
        return ['nama' => fake()->words(3, true), 'versi' => fake()->unique()->numerify('versi-####'), 'referensi' => 'Pedoman penilaian risiko', 'dibekukan' => false];
    }
}
