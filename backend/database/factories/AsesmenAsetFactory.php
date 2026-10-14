<?php

namespace Database\Factories;

use App\Models\AsesmenAset;
use App\Models\AsesmenRisiko;
use App\Models\Aset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AsesmenAset> */
class AsesmenAsetFactory extends Factory
{
    protected $model = AsesmenAset::class;

    public function definition(): array
    {
        return ['asesmen_id' => AsesmenRisiko::factory(), 'aset_id' => Aset::factory(), 'nama_snapshot' => 'Aset snapshot', 'kategori_snapshot' => 'Kategori snapshot', 'klasifikasi_informasi_snapshot' => '', 'jalur_unit_snapshot' => '[]', 'lokasi_snapshot' => 'Lab', 'nilai_kekritisan' => 1, 'dasar_kekritisan' => 'Dasar', 'direkam_pada' => now()];
    }
}
