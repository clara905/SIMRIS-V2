<?php

namespace Database\Factories;

use App\Models\SkalaDampak;
use App\Models\VersiMetodeRisiko;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SkalaDampak> */
class SkalaDampakFactory extends Factory
{
    protected $model = SkalaDampak::class;

    public function definition(): array
    {
        return ['versi_metode_id' => VersiMetodeRisiko::factory(), 'nilai' => 1, 'label' => 'Kecil', 'kriteria' => 'Dampak kecil'];
    }
}
