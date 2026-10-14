<?php

namespace Database\Factories;

use App\Models\SopReview;
use App\Models\SopVersi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SopReview> */
class SopReviewFactory extends Factory
{
    protected $model = SopReview::class;

    public function definition(): array
    {
        return ['sop_versi_id' => SopVersi::factory(), 'reviewer_id' => User::factory(), 'tahap' => 'KASUB', 'keputusan' => 'SETUJU', 'catatan' => 'Sesuai', 'waktu' => now()];
    }
}
