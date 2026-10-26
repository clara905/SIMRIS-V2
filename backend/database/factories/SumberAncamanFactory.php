<?php

namespace Database\Factories;

use App\Models\SumberAncaman;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SumberAncaman> */
class SumberAncamanFactory extends Factory
{
    protected $model = SumberAncaman::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true), 'deskripsi' => 'Deskripsi'];
    }
}
