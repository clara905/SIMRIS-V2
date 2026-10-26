<?php

namespace Database\Factories;

use App\Models\JenisKerentanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JenisKerentanan> */
class JenisKerentananFactory extends Factory
{
    protected $model = JenisKerentanan::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true)];
    }
}
