<?php

namespace Database\Factories;

use App\Models\PenilaianRisiko;
use App\Models\User;
use App\Models\UsulanKeputusan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UsulanKeputusan> */
class UsulanKeputusanFactory extends Factory
{
    protected $model = UsulanKeputusan::class;

    public function definition(): array
    {
        return ['penilaian_id' => PenilaianRisiko::factory(), 'diusulkan_oleh' => User::factory(), 'keputusan' => 'TERIMA', 'alasan' => 'Alasan', 'diusulkan_pada' => now()];
    }
}
