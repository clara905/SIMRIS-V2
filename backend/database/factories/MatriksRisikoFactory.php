<?php

namespace Database\Factories;

use App\Models\MatriksRisiko;
use App\Models\SkalaDampak;
use App\Models\SkalaKemungkinan;
use App\Models\TingkatRisiko;
use App\Models\VersiMetodeRisiko;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatriksRisiko> */
class MatriksRisikoFactory extends Factory
{
    protected $model = MatriksRisiko::class;

    public function definition(): array
    {
        return ['versi_metode_id' => VersiMetodeRisiko::factory(), 'kemungkinan_id' => fn (array $attributes): int => SkalaKemungkinan::factory()->create(['versi_metode_id' => $attributes['versi_metode_id']])->id, 'dampak_id' => fn (array $attributes): int => SkalaDampak::factory()->create(['versi_metode_id' => $attributes['versi_metode_id']])->id, 'tingkat_risiko_id' => fn (array $attributes): int => TingkatRisiko::factory()->create(['versi_metode_id' => $attributes['versi_metode_id']])->id, 'rekomendasi_keputusan' => 'TERIMA'];
    }
}
