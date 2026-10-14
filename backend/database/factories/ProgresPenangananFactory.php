<?php

namespace Database\Factories;

use App\Models\ProgresPenanganan;
use App\Models\RencanaPenanganan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProgresPenanganan> */
class ProgresPenangananFactory extends Factory
{
    protected $model = ProgresPenanganan::class;

    public function definition(): array
    {
        return ['rencana_penanganan_id' => RencanaPenanganan::factory(), 'dilaporkan_oleh' => User::factory(), 'uraian' => 'Uraian penanganan', 'status' => 'DIRENCANAKAN', 'dilaporkan_pada' => now()];
    }
}
