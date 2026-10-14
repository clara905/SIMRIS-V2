<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SimrisSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $roles = [];
            foreach (['SUPER_ADMIN', 'ADMIN_SATKER', 'KASATKER', 'KABID', 'KASUB', 'STAFF'] as $kode) {
                $roles[$kode] = $this->record('ROLES', ['kode' => $kode], ['nama' => ucwords(strtolower(str_replace('_', ' ', $kode)))]);
            }
            foreach (['username', 'email', 'password'] as $field) {
                if (! is_string(config("simris.admin.$field")) || trim(config("simris.admin.$field")) === '') {
                    throw new InvalidArgumentException('Isi SIMRIS_ADMIN_USERNAME, SIMRIS_ADMIN_EMAIL, dan SIMRIS_ADMIN_PASSWORD sebelum seeding.');
                }
            }
            $admin = User::where('username', config('simris.admin.username'))->first();
            if (! $admin) {
                $admin = User::create([
                    'username' => config('simris.admin.username'),
                    'email_login' => config('simris.admin.email'),
                    'password_hash' => config('simris.admin.password'),
                    'aktif' => true,
                    'wajib_ganti_password' => true,
                ]);
                $this->record('USER_ROLE_SCOPE', ['user_id' => $admin->id, 'role_id' => $roles['SUPER_ADMIN'], 'unit_id' => null], ['mulai' => now(), 'selesai' => null]);
            }

            $sumber = $this->record('SUMBER_ASET', ['kode' => 'API_ASET'], ['nama' => 'API aset eksternal']);
            $kategori = $this->record('KATEGORI_ASET', ['kode' => 'BELUM_DIKATEGORIKAN'], ['parent_id' => null, 'nama' => 'Belum dikategorikan']);
            if (app()->environment('local')) {
                $this->seedLocal($roles, $sumber, $kategori);
            }
        });
    }

    /** @param array<string, int> $roles */
    private function seedLocal(array $roles, int $sumber, int $kategori): void
    {
        $password = config('simris.demo_password');
        if (! is_string($password) || trim($password) === '') {
            throw new InvalidArgumentException('Isi SIMRIS_DEMO_PASSWORD untuk akun contoh lokal.');
        }
        $satker = $this->record('UNIT_ORGANISASI', ['kode' => config('simris.aset_unit_bawaan_kode')], ['parent_id' => null, 'nama' => 'Satker Contoh', 'jenis' => 'SATKER', 'aktif' => true]);
        $bidang = [];
        $subbidang = [];
        foreach ([1, 2] as $nomor) {
            $bidang[$nomor] = $this->record('UNIT_ORGANISASI', ['kode' => "BIDANG-DEMO-$nomor"], ['parent_id' => $satker, 'nama' => "Bidang Contoh $nomor", 'jenis' => 'BIDANG', 'aktif' => true]);
            $subbidang[$nomor] = $this->record('UNIT_ORGANISASI', ['kode' => "SUBBIDANG-DEMO-$nomor"], ['parent_id' => $bidang[$nomor], 'nama' => "Subbidang Contoh $nomor", 'jenis' => 'SUBBIDANG', 'aktif' => true]);
        }
        foreach (['ADMIN_SATKER' => $satker, 'KASATKER' => $satker, 'KABID' => $bidang[1], 'KASUB' => $subbidang[1], 'STAFF' => $subbidang[1]] as $role => $unit) {
            $email = strtolower($role).'@example.test';
            $pegawai = $this->record('PEGAWAI', ['email_kontak' => $email], ['nip' => null, 'nama' => "Contoh $role", 'jenis_kepegawaian' => 'NON_PNS', 'aktif' => true]);
            $this->record('PENEMPATAN_PEGAWAI', ['pegawai_id' => $pegawai, 'utama' => true, 'selesai' => null], ['unit_id' => $unit, 'mulai' => today()->toDateString()]);
            $user = User::firstOrCreate(['username' => strtolower($role).'-demo'], ['pegawai_id' => $pegawai, 'email_login' => $email, 'password_hash' => $password, 'aktif' => true, 'wajib_ganti_password' => true]);
            $this->record('USER_ROLE_SCOPE', ['user_id' => $user->id, 'role_id' => $roles[$role], 'unit_id' => $unit], ['mulai' => now(), 'selesai' => null]);
        }
        $versi = $this->record('VERSI_METODE_RISIKO', ['nama' => 'Metode contoh 5x5', 'versi' => '1'], ['referensi' => 'Data demonstrasi lokal', 'dibekukan' => false]);
        if (! DB::table('VERSI_METODE_RISIKO')->where('id', $versi)->value('dibekukan')) {
            $this->seedMethod($versi);
        }
        $jenisAncaman = $this->record('JENIS_ANCAMAN', ['kode' => 'TEKNIS'], ['nama' => 'Teknis']);
        $this->record('ANCAMAN', ['kode' => 'ANC-DEMO'], ['jenis_ancaman_id' => $jenisAncaman, 'nama' => 'Gangguan layanan', 'deskripsi' => 'Contoh ancaman lokal']);
        $jenisKerentanan = $this->record('JENIS_KERENTANAN', ['kode' => 'TEKNIS'], ['nama' => 'Teknis']);
        $this->record('KERENTANAN', ['kode' => 'KRT-DEMO'], ['jenis_kerentanan_id' => $jenisKerentanan, 'nama' => 'Pemeliharaan tertunda', 'deskripsi' => 'Contoh kerentanan lokal']);
        $standar = $this->record('STANDAR_KONTROL', ['nama' => 'Standar contoh', 'versi' => '1'], ['referensi' => 'Khusus demonstrasi']);
        $this->record('KONTROL', ['standar_id' => $standar, 'kode' => 'K-DEMO'], ['parent_id' => null, 'nama' => 'Pemeliharaan berkala', 'jenis' => 'KONTROL', 'deskripsi' => 'Contoh kontrol lokal']);
        $this->record('ASET', ['sumber_id' => $sumber, 'external_id' => 'DEMO-1'], ['unit_pemilik_id' => $satker, 'kategori_id' => $kategori, 'klasifikasi_informasi_id' => null, 'kode_aset' => 'DEMO-ASET-1', 'nama' => 'Perangkat contoh', 'deskripsi' => 'Data lokal; bukan hasil API', 'merk' => null, 'nomor_seri' => null, 'jumlah' => 1, 'kondisi' => 'BAIK', 'lokasi' => '', 'tanggal_pengadaan' => null, 'status' => 'AKTIF', 'last_synced_at' => now(), 'data_mentah' => '{}', 'hash_data' => hash('sha256', '{}'), 'perlu_ditinjau' => true]);
        $this->record('PERIODE_PENILAIAN', ['nama' => 'Periode contoh '.now()->year], ['tanggal_mulai' => now()->startOfYear()->toDateString(), 'tanggal_selesai' => now()->endOfYear()->toDateString()]);
    }

    private function seedMethod(int $versi): void
    {
        $area = $this->record('AREA_DAMPAK', ['kode' => 'OPERASIONAL'], ['nama' => 'Operasional']);
        $tingkat = [];
        foreach (['RENDAH' => '#15803d', 'SEDANG' => '#b45309', 'TINGGI' => '#b91c1c'] as $kode => $warna) {
            $tingkat[$kode] = $this->record('TINGKAT_RISIKO', ['versi_metode_id' => $versi, 'kode' => $kode], ['nama' => ucfirst(strtolower($kode)), 'warna' => $warna]);
        }
        $kemungkinan = [];
        $dampak = [];
        foreach (range(1, 5) as $nilai) {
            $kemungkinan[$nilai] = $this->record('SKALA_KEMUNGKINAN', ['versi_metode_id' => $versi, 'nilai' => $nilai], ['label' => "Kemungkinan $nilai", 'label_persentase' => ($nilai * 20).'%', 'label_frekuensi' => "Contoh frekuensi $nilai", 'kriteria' => "Contoh kriteria kemungkinan $nilai"]);
            $dampak[$nilai] = $this->record('SKALA_DAMPAK', ['versi_metode_id' => $versi, 'nilai' => $nilai], ['label' => "Dampak $nilai", 'kriteria' => "Contoh kriteria dampak $nilai"]);
            foreach (['P', 'N'] as $arah) {
                $this->record('KRITERIA_DAMPAK', ['skala_dampak_id' => $dampak[$nilai], 'area_dampak_id' => $area, 'arah_dampak' => $arah], ['deskripsi' => "Contoh dampak $arah tingkat $nilai"]);
            }
        }
        foreach ($kemungkinan as $k => $kemungkinanId) {
            foreach ($dampak as $d => $dampakId) {
                $skor = $k * $d;
                $this->record('MATRIKS_RISIKO', ['versi_metode_id' => $versi, 'kemungkinan_id' => $kemungkinanId, 'dampak_id' => $dampakId], ['tingkat_risiko_id' => $tingkat[$skor <= 4 ? 'RENDAH' : ($skor <= 12 ? 'SEDANG' : 'TINGGI')], 'skor' => $skor, 'rekomendasi_keputusan' => $skor <= 4 ? 'TERIMA' : 'MITIGASI']);
            }
        }
        DB::table('VERSI_METODE_RISIKO')->where('id', $versi)->update(['dibekukan' => true, 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    private function record(string $table, array $key, array $values): int
    {
        $id = DB::table($table)->where($key)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        return DB::table($table)->insertGetId([...$key, ...$values, 'created_at' => now(), 'updated_at' => now()]);
    }
}
