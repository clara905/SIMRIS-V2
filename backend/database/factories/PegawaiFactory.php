<?php

namespace Database\Factories;

use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pegawai> */
class PegawaiFactory extends Factory
{
    protected $model = Pegawai::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['nip' => fake()->unique()->numerify('##################'), 'nama' => fake()->name(), 'email_kontak' => fake()->safeEmail(), 'jenis_kepegawaian' => 'PNS', 'aktif' => true];
    }
}
