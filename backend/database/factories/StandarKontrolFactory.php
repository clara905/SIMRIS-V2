<?php

namespace Database\Factories;

use App\Models\StandarKontrol;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StandarKontrol> */
class StandarKontrolFactory extends Factory
{
    protected $model = StandarKontrol::class;

    public function definition(): array
    {
        return ['nama' => fake()->words(3, true), 'versi' => '1', 'referensi' => 'Referensi'];
    }
}
