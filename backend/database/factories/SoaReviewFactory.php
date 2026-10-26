<?php

namespace Database\Factories;

use App\Models\SoaReview;
use App\Models\SoaVersi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SoaReview> */
class SoaReviewFactory extends Factory
{
    protected $model = SoaReview::class;

    public function definition(): array
    {
        return ['soa_versi_id' => SoaVersi::factory(), 'reviewer_id' => User::factory(), 'tahap' => 'KASUB', 'keputusan' => 'SETUJU', 'catatan' => 'Sesuai', 'waktu' => now()];
    }
}
