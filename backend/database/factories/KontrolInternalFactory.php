<?php

namespace Database\Factories;

use App\Models\KontrolInternal;
use App\Models\UnitOrganisasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KontrolInternal> */
class KontrolInternalFactory extends Factory
{
    protected $model = KontrolInternal::class;

    public function definition(): array
    {
        return ['unit_pemilik_id' => UnitOrganisasi::factory(), 'kode' => fake()->unique()->bothify('KI-####'), 'nama' => 'Kontrol internal', 'jenis' => 'TEKNIS', 'deskripsi' => 'Deskripsi kontrol', 'aktif' => true];
    }
}
