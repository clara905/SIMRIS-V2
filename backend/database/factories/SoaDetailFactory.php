<?php

namespace Database\Factories;

use App\Models\Kontrol;
use App\Models\Soa;
use App\Models\SoaDetail;
use App\Models\SoaVersi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SoaDetail> */
class SoaDetailFactory extends Factory
{
    protected $model = SoaDetail::class;

    public function definition(): array
    {
        return ['soa_versi_id' => SoaVersi::factory(), 'kontrol_id' => fn (array $attributes): int => Kontrol::factory()->create(['standar_id' => Soa::findOrFail(SoaVersi::findOrFail($attributes['soa_versi_id'])->soa_id)->standar_id])->id, 'penanggung_jawab_id' => null, 'berlaku' => true, 'alasan' => 'Relevan', 'status_penerapan' => 'BELUM_DITERAPKAN'];
    }
}
