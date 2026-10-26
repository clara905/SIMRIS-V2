<?php

namespace Database\Factories;

use App\Models\SeriAsesmen;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeriAsesmen> */
class SeriAsesmenFactory extends Factory
{
    protected $model = SeriAsesmen::class;

    public function definition(): array
    {
        return ['kode' => fake()->unique()->numerify('KODE-########'), 'nama' => 'Seri asesmen'];
    }
}
