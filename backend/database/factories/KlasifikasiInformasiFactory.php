<?php

namespace Database\Factories;

use App\Models\KlasifikasiInformasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KlasifikasiInformasi> */
class KlasifikasiInformasiFactory extends Factory
{
    protected $model = KlasifikasiInformasi::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true), 'deskripsi' => 'Deskripsi'];
    }
}
