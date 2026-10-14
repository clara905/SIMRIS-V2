<?php

namespace Database\Factories;

use App\Models\Pegawai;
use App\Models\PenempatanPegawai;
use App\Models\UnitOrganisasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PenempatanPegawai> */
class PenempatanPegawaiFactory extends Factory
{
    protected $model = PenempatanPegawai::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['pegawai_id' => Pegawai::factory(), 'unit_id' => UnitOrganisasi::factory(), 'mulai' => today()->toDateString(), 'selesai' => null, 'utama' => true];
    }
}
