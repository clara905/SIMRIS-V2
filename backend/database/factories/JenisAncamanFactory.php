<?php

namespace Database\Factories;

use App\Models\JenisAncaman;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JenisAncaman> */
class JenisAncamanFactory extends Factory
{
    protected $model = JenisAncaman::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true)];
    }
}
