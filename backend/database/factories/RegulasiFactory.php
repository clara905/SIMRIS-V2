<?php

namespace Database\Factories;

use App\Models\Regulasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Regulasi> */
class RegulasiFactory extends Factory
{
    protected $model = Regulasi::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true), 'referensi' => 'Referensi'];
    }
}
