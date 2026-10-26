<?php

namespace Database\Factories;

use App\Models\Ancaman;
use App\Models\AreaDampak;
use App\Models\AsesmenRisiko;
use App\Models\Kerentanan;
use App\Models\Pegawai;
use App\Models\Risiko;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Risiko> */
class RisikoFactory extends Factory
{
    protected $model = Risiko::class;

    public function definition(): array
    {
        return ['asesmen_id' => AsesmenRisiko::factory(), 'ancaman_id' => Ancaman::factory(), 'kerentanan_id' => Kerentanan::factory(), 'area_dampak_id' => AreaDampak::factory(), 'pemilik_risiko_id' => Pegawai::factory(), 'dibuat_oleh' => User::factory(), 'kode' => fake()->unique()->numerify('KODE-########'), 'skenario' => 'Skenario', 'uraian_kerentanan' => 'Kerentanan', 'uraian_dampak' => 'Dampak', 'arah_dampak' => 'N', 'dibuat_pada' => now()];
    }
}
