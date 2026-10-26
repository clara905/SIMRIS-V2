<?php

namespace Database\Factories;

use App\Models\AreaDampak;
use App\Models\KriteriaDampak;
use App\Models\SkalaDampak;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KriteriaDampak> */
class KriteriaDampakFactory extends Factory
{
    protected $model = KriteriaDampak::class;

    public function definition(): array
    {
        return ['skala_dampak_id' => SkalaDampak::factory(), 'area_dampak_id' => AreaDampak::factory(), 'arah_dampak' => 'N', 'deskripsi' => 'Gangguan operasional ringan'];
    }
}
