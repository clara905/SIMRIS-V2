<?php

namespace Database\Factories;

use App\Models\AsesmenRisiko;
use App\Models\MatriksRisiko;
use App\Models\PenilaianRisiko;
use App\Models\Risiko;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PenilaianRisiko> */
class PenilaianRisikoFactory extends Factory
{
    protected $model = PenilaianRisiko::class;

    public function definition(): array
    {
        return ['risiko_id' => Risiko::factory(), 'matriks_id' => fn (array $attributes): int => MatriksRisiko::where('versi_metode_id', AsesmenRisiko::findOrFail(Risiko::findOrFail($attributes['risiko_id'])->asesmen_id)->versi_metode_id)->firstOrFail()->id, 'dinilai_oleh' => User::factory(), 'tahap' => 'SAAT_INI', 'alasan_kemungkinan' => 'Alasan kemungkinan', 'alasan_dampak' => 'Alasan dampak', 'kesimpulan' => 'Kesimpulan', 'dinilai_pada' => now()];
    }
}
