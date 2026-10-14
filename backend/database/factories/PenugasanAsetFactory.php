<?php

namespace Database\Factories;

use App\Models\Aset;
use App\Models\Pegawai;
use App\Models\PenugasanAset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PenugasanAset> */
class PenugasanAsetFactory extends Factory
{
    protected $model = PenugasanAset::class;

    public function definition(): array
    {
        return ['aset_id' => Aset::factory(), 'pegawai_id' => Pegawai::factory(), 'jenis_tanggung_jawab' => 'PENGGUNA', 'mulai' => today(), 'selesai' => null];
    }
}
