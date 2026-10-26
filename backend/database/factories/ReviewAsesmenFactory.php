<?php

namespace Database\Factories;

use App\Models\AsesmenRisiko;
use App\Models\ReviewAsesmen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewAsesmen> */
class ReviewAsesmenFactory extends Factory
{
    protected $model = ReviewAsesmen::class;

    public function definition(): array
    {
        return ['asesmen_id' => AsesmenRisiko::factory(), 'reviewer_id' => User::factory(), 'tahap' => 'KASUB_AWAL', 'keputusan' => 'SETUJU', 'catatan' => 'Disetujui', 'kembali_ke' => null, 'waktu' => now()];
    }
}
