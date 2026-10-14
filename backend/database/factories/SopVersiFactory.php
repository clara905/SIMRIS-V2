<?php

namespace Database\Factories;

use App\Models\DokumenVersi;
use App\Models\Sop;
use App\Models\SopVersi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SopVersi> */
class SopVersiFactory extends Factory
{
    protected $model = SopVersi::class;

    public function definition(): array
    {
        return ['sop_id' => Sop::factory(), 'dokumen_versi_id' => function (): int {
            $document = DokumenVersi::factory()->create();
            $document->update(['status' => 'FINAL']);

            return $document->id;
        }, 'dibuat_oleh' => User::factory(), 'nomor_versi' => '1', 'tanggal_berlaku' => today(), 'status' => 'DRAFT', 'dibuat_pada' => now()];
    }
}
