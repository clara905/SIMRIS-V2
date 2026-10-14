<?php

namespace Database\Factories;

use App\Models\SumberAset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SumberAset> */
class SumberAsetFactory extends Factory
{
    protected $model = SumberAset::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true)];
    }
}
