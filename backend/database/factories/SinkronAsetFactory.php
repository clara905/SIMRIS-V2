<?php

namespace Database\Factories;

use App\Models\SinkronAset;
use App\Models\SumberAset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SinkronAset> */
class SinkronAsetFactory extends Factory
{
    protected $model = SinkronAset::class;

    public function definition(): array
    {
        return ['sumber_id' => SumberAset::factory(), 'dipicu_oleh' => null, 'dimulai_pada' => now(), 'selesai_pada' => null, 'status' => 'BERJALAN', 'jumlah_diterima' => 0, 'jumlah_dibuat' => 0, 'jumlah_diubah' => 0, 'jumlah_tidak_berubah' => 0, 'jumlah_tidak_ditemukan' => 0, 'jumlah_ditolak' => 0, 'peringatan' => [], 'galat' => null];
    }
}
