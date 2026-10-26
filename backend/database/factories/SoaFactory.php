<?php

namespace Database\Factories;

use App\Models\PeriodePenilaian;
use App\Models\Soa;
use App\Models\StandarKontrol;
use App\Models\UnitOrganisasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Soa> */
class SoaFactory extends Factory
{
    protected $model = Soa::class;

    public function definition(): array
    {
        return ['unit_id' => UnitOrganisasi::factory(), 'periode_id' => PeriodePenilaian::factory(), 'standar_id' => StandarKontrol::factory(), 'ruang_lingkup' => 'Layanan unit'];
    }
}
