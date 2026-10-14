<?php

namespace Database\Factories;

use App\Models\Kontrol;
use App\Models\StandarKontrol;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Kontrol> */
class KontrolFactory extends Factory
{
    protected $model = Kontrol::class;

    public function definition(): array
    {
        return ['standar_id' => StandarKontrol::factory(), 'parent_id' => null, 'kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true), 'jenis' => 'KONTROL', 'deskripsi' => 'Deskripsi'];
    }
}
