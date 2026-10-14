<?php

namespace Database\Factories;

use App\Models\RencanaKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RencanaKerja> */
class RencanaKerjaFactory extends Factory
{
    protected $model = RencanaKerja::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('RK-######'), 'uraian_template' => fake()->sentence()];
    }
}
