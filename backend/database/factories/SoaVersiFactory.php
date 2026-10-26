<?php

namespace Database\Factories;

use App\Models\Soa;
use App\Models\SoaVersi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SoaVersi> */
class SoaVersiFactory extends Factory
{
    protected $model = SoaVersi::class;

    public function definition(): array
    {
        return ['soa_id' => Soa::factory(), 'dibuat_oleh' => User::factory(), 'nomor_versi' => '1', 'status' => 'DRAFT', 'dibuat_pada' => now(), 'dibekukan_pada' => null];
    }
}
