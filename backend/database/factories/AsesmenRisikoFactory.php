<?php

namespace Database\Factories;

use App\Models\AsesmenRisiko;
use App\Models\PeriodePenilaian;
use App\Models\SeriAsesmen;
use App\Models\UnitOrganisasi;
use App\Models\User;
use App\Models\VersiMetodeRisiko;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AsesmenRisiko> */
class AsesmenRisikoFactory extends Factory
{
    protected $model = AsesmenRisiko::class;

    public function definition(): array
    {
        return ['seri_asesmen_id' => SeriAsesmen::factory(), 'versi_sebelumnya_id' => null, 'periode_id' => PeriodePenilaian::factory(), 'unit_id' => UnitOrganisasi::factory(), 'versi_metode_id' => VersiMetodeRisiko::factory()->frozen(), 'dibuat_oleh' => User::factory(), 'tujuan' => 'Tujuan', 'ruang_lingkup' => 'Lingkup', 'status' => 'DRAFT', 'nomor_versi' => 1, 'dibuat_pada' => now(), 'dibekukan_pada' => null, 'dibekukan_awal_pada' => null];
    }
}
