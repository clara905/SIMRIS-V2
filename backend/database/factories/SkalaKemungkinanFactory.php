<?php

namespace Database\Factories;

use App\Models\SkalaKemungkinan;
use App\Models\VersiMetodeRisiko;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SkalaKemungkinan> */
class SkalaKemungkinanFactory extends Factory
{
    protected $model = SkalaKemungkinan::class;

    public function definition(): array
    {
        return ['versi_metode_id' => VersiMetodeRisiko::factory(), 'nilai' => 1, 'label' => 'Jarang', 'label_persentase' => '0?20%', 'label_frekuensi' => 'Tahunan', 'kriteria' => 'Kemungkinan rendah'];
    }
}
