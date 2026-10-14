<?php

namespace Database\Factories;

use App\Models\PeriodePenilaian;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeriodePenilaian> */
class PeriodePenilaianFactory extends Factory
{
    protected $model = PeriodePenilaian::class;

    public function definition(): array
    {
        return ['nama' => 'Periode '.fake()->year(), 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31'];
    }
}
