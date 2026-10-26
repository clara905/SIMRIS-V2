<?php

namespace Database\Factories;

use App\Models\Sop;
use App\Models\UnitOrganisasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sop> */
class SopFactory extends Factory
{
    protected $model = Sop::class;

    public function definition(): array
    {
        return ['unit_pemilik_id' => UnitOrganisasi::factory(), 'kode' => fake()->unique()->bothify('SOP-####'), 'judul' => 'Prosedur operasi'];
    }
}
