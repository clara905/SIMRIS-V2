<?php

namespace Database\Factories;

use App\Models\Dokumen;
use App\Models\UnitOrganisasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dokumen> */
class DokumenFactory extends Factory
{
    protected $model = Dokumen::class;

    public function definition(): array
    {
        return ['unit_pemilik_id' => UnitOrganisasi::factory(), 'dibuat_oleh' => User::factory(), 'kode' => fake()->unique()->numerify('DOK-########'), 'judul' => 'Dokumen uji', 'jenis' => 'BUKTI', 'dibuat_pada' => now()];
    }
}
