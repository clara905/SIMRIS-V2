<?php

namespace Database\Factories;

use App\Models\JenisKerentanan;
use App\Models\Kerentanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Kerentanan> */
class KerentananFactory extends Factory
{
    protected $model = Kerentanan::class;

    public function definition(): array
    {
        return ['jenis_kerentanan_id' => JenisKerentanan::factory(), 'kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true), 'deskripsi' => 'Deskripsi'];
    }
}
