<?php

namespace Database\Factories;

use App\Models\Aset;
use App\Models\KategoriAset;
use App\Models\SumberAset;
use App\Models\UnitOrganisasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Aset> */
class AsetFactory extends Factory
{
    protected $model = Aset::class;

    public function definition(): array
    {
        return ['sumber_id' => SumberAset::factory(), 'external_id' => fake()->unique()->numerify('########'), 'unit_pemilik_id' => UnitOrganisasi::factory(), 'kategori_id' => KategoriAset::factory(), 'klasifikasi_informasi_id' => null, 'kode_aset' => fake()->unique()->bothify('ASET-########'), 'nama' => fake()->words(3, true), 'deskripsi' => 'Deskripsi', 'merk' => 'Merk', 'nomor_seri' => null, 'jumlah' => 1, 'kondisi' => 'BAIK', 'lokasi' => '', 'tanggal_pengadaan' => null, 'status' => 'AKTIF', 'last_synced_at' => now(), 'nomor_inventaris' => null, 'nama_pengadaan' => null, 'tahun_pengadaan' => null, 'keterangan' => null, 'data_mentah' => [], 'hash_data' => hash('sha256', 'test'), 'perlu_ditinjau' => true];
    }
}
