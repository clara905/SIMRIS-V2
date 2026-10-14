<?php

namespace Database\Factories;

use App\Models\AreaDampak;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AreaDampak> */
class AreaDampakFactory extends Factory
{
    protected $model = AreaDampak::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->numerify('AREA-#####'), 'nama' => 'Area operasional'];
    }
}
