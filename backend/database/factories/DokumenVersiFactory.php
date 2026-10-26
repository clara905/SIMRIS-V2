<?php

namespace Database\Factories;

use App\Models\Dokumen;
use App\Models\DokumenVersi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DokumenVersi> */
class DokumenVersiFactory extends Factory
{
    protected $model = DokumenVersi::class;

    public function definition(): array
    {
        return ['dokumen_id' => Dokumen::factory(), 'dibuat_oleh' => User::factory(), 'nomor_versi' => 1, 'isi_teks' => 'Isi dokumen uji', 'format_teks' => 'PLAIN', 'status' => 'DRAFT', 'dibuat_pada' => now(), 'dibekukan_pada' => null];
    }
}
