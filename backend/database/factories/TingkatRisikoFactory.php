<?php

namespace Database\Factories;

use App\Models\TingkatRisiko;
use App\Models\VersiMetodeRisiko;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TingkatRisiko> */
class TingkatRisikoFactory extends Factory
{
    protected $model = TingkatRisiko::class;

    public function definition(): array
    {
        return ['versi_metode_id' => VersiMetodeRisiko::factory(), 'kode' => 'RENDAH', 'nama' => 'Rendah', 'warna' => '#008800'];
    }
}
