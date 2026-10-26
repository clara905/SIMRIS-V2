<?php

namespace Database\Factories;

use App\Models\Regulasi;
use App\Models\SoaBenchmark;
use App\Models\SoaDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SoaBenchmark> */
class SoaBenchmarkFactory extends Factory
{
    protected $model = SoaBenchmark::class;

    public function definition(): array
    {
        return ['soa_detail_id' => SoaDetail::factory(), 'regulasi_id' => Regulasi::factory(), 'rujukan' => 'Pasal 1'];
    }
}
