<?php

namespace Database\Factories;

use App\Models\KategoriAset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KategoriAset> */
class KategoriAsetFactory extends Factory
{
    protected $model = KategoriAset::class;

    public function definition(): array
    {
        return ['parent_id' => null, 'kode' => fake()->unique()->bothify('K-########'), 'nama' => fake()->words(3, true)];
    }
}
