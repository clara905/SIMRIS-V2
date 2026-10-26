<?php

namespace Database\Factories;

use App\Models\FileLampiran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FileLampiran> */
class FileLampiranFactory extends Factory
{
    protected $model = FileLampiran::class;

    public function definition(): array
    {
        return ['diunggah_oleh' => User::factory(), 'nama_asli' => 'bukti.txt', 'storage_key' => 'lampiran/'.fake()->uuid(), 'ekstensi' => 'txt', 'mime_type_terdeteksi' => 'text/plain', 'ukuran_byte' => 4, 'checksum_sha256' => hash('sha256', 'test'), 'status_pemeriksaan' => 'PENDING', 'diunggah_pada' => now()];
    }
}
