<?php

namespace Database\Factories;

use App\Models\AsesmenRisiko;
use App\Models\Pegawai;
use App\Models\RencanaPenanganan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RencanaPenanganan> */
class RencanaPenangananFactory extends Factory
{
    protected $model = RencanaPenanganan::class;

    public function definition(): array
    {
        return ['asesmen_id' => AsesmenRisiko::factory()->state(['status' => 'PENANGANAN', 'dibekukan_awal_pada' => now()]), 'rencana_kerja_id' => null, 'pic_id' => Pegawai::factory(), 'kode' => fake()->unique()->numerify('RP-########'), 'uraian' => 'Uraian penanganan', 'target_selesai' => today()->addMonth(), 'status' => 'DIRENCANAKAN'];
    }
}
