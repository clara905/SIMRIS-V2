<?php

namespace Database\Factories;

use App\Models\Ancaman;
use App\Models\JenisAncaman;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ancaman> */
class AncamanFactory extends Factory
{
    protected $model = Ancaman::class;

    public function definition(): array
    {
        return ['jenis_ancaman_id' => JenisAncaman::factory(), 'kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true), 'deskripsi' => 'Deskripsi'];
    }
}
