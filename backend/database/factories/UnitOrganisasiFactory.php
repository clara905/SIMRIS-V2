<?php

namespace Database\Factories;

use App\Models\UnitOrganisasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UnitOrganisasi> */
class UnitOrganisasiFactory extends Factory
{
    protected $model = UnitOrganisasi::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['parent_id' => null, 'kode' => fake()->unique()->bothify('UNIT-########'), 'nama' => fake()->company(), 'jenis' => 'SATKER', 'aktif' => true];
    }
}
