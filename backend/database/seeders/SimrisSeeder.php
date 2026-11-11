<?php

namespace Database\Seeders;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SimrisSeeder extends Seeder
{
    public function run(): void
    {
        // Validate all required credentials before opening a transaction or
        // writing any seed data. This keeps a missing configuration a clean,
        // predictable input error rather than a partial seed attempt.
        foreach (['username', 'email', 'password'] as $field) {
            $value = config("simris.admin.$field");

            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException(
                    'Isi SIMRIS_ADMIN_USERNAME, SIMRIS_ADMIN_EMAIL, dan SIMRIS_ADMIN_PASSWORD sebelum seeding.'
                );
            }
        }

        if (app()->environment('local')) {
            $demoPassword = config('simris.demo_password');

            if (!is_string($demoPassword) || trim($demoPassword) === '') {
                throw new InvalidArgumentException(
                    'Isi SIMRIS_DEMO_PASSWORD untuk akun contoh lokal.'
                );
            }
        }

        DB::transaction(function (): void {

            /*
            |--------------------------------------------------------------------------
            | 1. SEED ROLE
            |--------------------------------------------------------------------------
            */

            $roles = [];

            foreach ([
                'SUPER_ADMIN',
                'ADMIN_SATKER',
                'KASATKER',
                'KABID',
                'KASUB',
                'STAFF',
            ] as $kode) {

                $roles[$kode] = $this->record(
                    'ROLES',
                    ['kode' => $kode],
                    [
                        'nama' => ucwords(
                            strtolower(str_replace('_', ' ', $kode))
                        ),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 3. SEED SUPER ADMIN
            |--------------------------------------------------------------------------
            */

            $adminUsername = config('simris.admin.username');
            $adminNip = config('simris.admin.nip');
            $adminEmployee = null;

            if ($adminNip !== null && $adminNip !== '') {
                if (! is_string($adminNip) || ! preg_match('/^[0-9]{1,18}$/', $adminNip)) {
                    throw new InvalidArgumentException('SIMRIS_ADMIN_NIP harus berisi 1 sampai 18 digit angka.');
                }

                $adminName = config('simris.admin.name');
                if (! is_string($adminName) || trim($adminName) === '') {
                    throw new InvalidArgumentException('Isi SIMRIS_ADMIN_NAME jika SIMRIS_ADMIN_NIP digunakan.');
                }

                $adminEmployee = Pegawai::query()->where('nip', $adminNip)->first();
                if (! $adminEmployee) {
                    $adminEmployee = Pegawai::query()->create([
                        'nip' => $adminNip,
                        'nama' => trim($adminName),
                        'email_kontak' => config('simris.admin.email'),
                        'jenis_kepegawaian' => config('simris.admin.employment_type', 'PNS'),
                        'aktif' => true,
                    ]);
                }
            }

            $admin = User::where('username', $adminUsername)->first();

            if (!$admin) {

                $admin = User::create([
                    'pegawai_id' => $adminEmployee?->id,
                    'username' => $adminUsername,
                    'email_login' => config('simris.admin.email'),
                    'password_hash' => config('simris.admin.password'),
                    'aktif' => true,
                    'wajib_ganti_password' => true,
                ]);
            } elseif ($adminEmployee) {
                if ($admin->pegawai_id !== null && (int) $admin->pegawai_id !== (int) $adminEmployee->id) {
                    throw new InvalidArgumentException('Akun SUPER_ADMIN yang sudah ada tertaut ke pegawai berbeda.');
                }

                if ($admin->pegawai_id === null) {
                    $admin->forceFill(['pegawai_id' => $adminEmployee->id])->save();
                }
            }

            $this->record(
                'USER_ROLE_SCOPE',
                [
                    'user_id' => $admin->id,
                    'role_id' => $roles['SUPER_ADMIN'],
                    'unit_id' => null,
                ],
                [
                    'mulai' => now(),
                    'selesai' => null,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 4. SEED SUMBER DAN KATEGORI ASET
            |--------------------------------------------------------------------------
            */

            $sumber = $this->record(
                'SUMBER_ASET',
                ['kode' => 'API_ASET'],
                ['nama' => 'API aset eksternal']
            );

            $kategori = $this->record(
                'KATEGORI_ASET',
                ['kode' => 'BELUM_DIKATEGORIKAN'],
                [
                    'parent_id' => null,
                    'nama' => 'Belum dikategorikan',
                ]
            );

            $this->seedAssetCatalogs();

            /*
            |--------------------------------------------------------------------------
            | 5. SEED DATA DEMO KHUSUS LOCAL
            |--------------------------------------------------------------------------
            */

            if (app()->environment('local')) {
                $this->seedLocal($roles, $sumber, $kategori);
            }
        });
    }

    private function seedAssetCatalogs(): void
    {
        $categories = [
            'PERANGKAT_KERAS' => [
                'nama' => 'Perangkat Keras',
                'children' => [
                    'SERVER' => 'Server',
                    'KOMPUTER_LAPTOP' => 'Komputer & Laptop',
                    'PERANGKAT_JARINGAN' => 'Perangkat Jaringan',
                    'PERANGKAT_PENYIMPANAN' => 'Perangkat Penyimpanan',
                    'PERANGKAT_PERIFERAL' => 'Perangkat Periferal',
                ],
            ],
            'PERANGKAT_LUNAK' => [
                'nama' => 'Perangkat Lunak',
                'children' => [
                    'SISTEM_OPERASI' => 'Sistem Operasi',
                    'APLIKASI' => 'Aplikasi',
                    'BASIS_DATA' => 'Basis Data',
                ],
            ],
            'INFORMASI_DATA' => [
                'nama' => 'Informasi & Data',
                'children' => [
                    'DOKUMEN_DIGITAL' => 'Dokumen Digital',
                    'DOKUMEN_FISIK' => 'Dokumen Fisik',
                    'BASIS_DATA_OPERASIONAL' => 'Basis Data Operasional',
                ],
            ],
            'LAYANAN' => [
                'nama' => 'Layanan',
                'children' => [
                    'LAYANAN_JARINGAN' => 'Layanan Jaringan',
                    'LAYANAN_APLIKASI' => 'Layanan Aplikasi',
                ],
            ],
            'SUMBER_DAYA_MANUSIA' => [
                'nama' => 'Sumber Daya Manusia',
                'children' => [],
            ],
            'FASILITAS' => [
                'nama' => 'Fasilitas',
                'children' => [
                    'RUANG_SERVER' => 'Ruang Server',
                    'PERANGKAT_PENDUKUNG_FISIK' => 'Perangkat Pendukung Fisik',
                ],
            ],
        ];

        foreach ($categories as $kode => $category) {
            $parentId = $this->record(
                'KATEGORI_ASET',
                ['kode' => $kode],
                ['parent_id' => null, 'nama' => $category['nama']]
            );
            foreach ($category['children'] as $childCode => $childName) {
                $this->record(
                    'KATEGORI_ASET',
                    ['kode' => $childCode],
                    ['parent_id' => $parentId, 'nama' => $childName]
                );
            }
        }

        foreach ([
            'PUBLIK' => [
                'nama' => 'Publik',
                'deskripsi' => 'Informasi yang boleh diketahui siapa saja dan tidak berdampak bila tersebar.',
            ],
            'INTERNAL' => [
                'nama' => 'Internal',
                'deskripsi' => 'Informasi untuk lingkungan organisasi; penyebaran ke luar dapat mengganggu operasional ringan.',
            ],
            'RAHASIA' => [
                'nama' => 'Rahasia',
                'deskripsi' => 'Informasi untuk pihak tertentu; kebocoran berdampak serius pada organisasi atau pihak terkait.',
            ],
            'SANGAT_RAHASIA' => [
                'nama' => 'Sangat rahasia',
                'deskripsi' => 'Informasi paling sensitif; kebocoran berdampak sangat berat dan sulit dipulihkan.',
            ],
        ] as $kode => $classification) {
            $this->record('KLASIFIKASI_INFORMASI', ['kode' => $kode], $classification);
        }
    }

    /**
     * Seed data lokal.
     *
     * @param array<string, int> $roles
     */
    private function seedLocal(
        array $roles,
        int $sumber,
        int $kategori
    ): void {

        $password = (string) config('simris.demo_password');

        /*
        |--------------------------------------------------------------------------
        | UNIT ORGANISASI
        |--------------------------------------------------------------------------
        */

        $satker = $this->record(
            'UNIT_ORGANISASI',
            [
                'kode' => config('simris.aset_unit_bawaan_kode'),
            ],
            [
                'parent_id' => null,
                'nama' => 'Satker Contoh',
                'jenis' => 'SATKER',
                'aktif' => true,
            ]
        );

        $bidang = [];
        $subbidang = [];

        foreach ([1, 2] as $nomor) {

            $bidang[$nomor] = $this->record(
                'UNIT_ORGANISASI',
                [
                    'kode' => "BIDANG-DEMO-$nomor",
                ],
                [
                    'parent_id' => $satker,
                    'nama' => "Bidang Contoh $nomor",
                    'jenis' => 'BIDANG',
                    'aktif' => true,
                ]
            );

            $subbidang[$nomor] = $this->record(
                'UNIT_ORGANISASI',
                [
                    'kode' => "SUBBIDANG-DEMO-$nomor",
                ],
                [
                    'parent_id' => $bidang[$nomor],
                    'nama' => "Subbidang Contoh $nomor",
                    'jenis' => 'SUBBIDANG',
                    'aktif' => true,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DATA PEGAWAI, PENEMPATAN, DAN USER
        |--------------------------------------------------------------------------
        */

        $akunDemo = [
            'ADMIN_SATKER' => $satker,
            'KASATKER' => $satker,
            'KABID' => $bidang[1],
            'KASUB' => $subbidang[1],
            'STAFF' => $subbidang[1],
        ];

        foreach ($akunDemo as $role => $unit) {

            $email = strtolower($role) . '@example.test';

            /*
            | PEGAWAI
            */

            $pegawai = $this->record(
                'PEGAWAI',
                [
                    'email_kontak' => $email,
                ],
                [
                    'nip' => null,
                    'nama' => "Contoh $role",
                    'jenis_kepegawaian' => 'NON_PNS',
                    'aktif' => true,
                ]
            );

            /*
            | PENEMPATAN PEGAWAI
            */

            $this->record(
                'PENEMPATAN_PEGAWAI',
                [
                    'pegawai_id' => $pegawai,
                    'utama' => true,
                    'selesai' => null,
                ],
                [
                    'unit_id' => $unit,
                    'mulai' => today()->toDateString(),
                ]
            );

            /*
            | USER
            */

            $user = $this->seedDemoUser(
                username: strtolower($role) . '-demo',
                pegawaiId: $pegawai,
                email: $email,
                password: $password
            );

            /*
            | USER ROLE SCOPE
            */

            $this->record(
                'USER_ROLE_SCOPE',
                [
                    'user_id' => $user->id,
                    'role_id' => $roles[$role],
                    'unit_id' => $unit,
                ],
                [
                    'mulai' => now(),
                    'selesai' => null,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERSI METODE RISIKO
        |--------------------------------------------------------------------------
        */

        $versi = $this->record(
            'VERSI_METODE_RISIKO',
            [
                'nama' => 'Metode contoh 5x5',
                'versi' => '1',
            ],
            [
                'referensi' => 'Data demonstrasi lokal',
                'dibekukan' => false,
            ]
        );

        $dibekukan = DB::table('VERSI_METODE_RISIKO')
            ->where('id', $versi)
            ->value('dibekukan');

        if (!$dibekukan) {
            $this->seedMethod($versi);
        }

        /*
        |--------------------------------------------------------------------------
        | ANCAMAN
        |--------------------------------------------------------------------------
        */

        $jenisAncaman = $this->record(
            'JENIS_ANCAMAN',
            ['kode' => 'TEKNIS'],
            ['nama' => 'Teknis']
        );

        $this->record(
            'ANCAMAN',
            ['kode' => 'ANC-DEMO'],
            [
                'jenis_ancaman_id' => $jenisAncaman,
                'nama' => 'Gangguan layanan',
                'deskripsi' => 'Contoh ancaman lokal',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | KERENTANAN
        |--------------------------------------------------------------------------
        */

        $jenisKerentanan = $this->record(
            'JENIS_KERENTANAN',
            ['kode' => 'TEKNIS'],
            ['nama' => 'Teknis']
        );

        $this->record(
            'KERENTANAN',
            ['kode' => 'KRT-DEMO'],
            [
                'jenis_kerentanan_id' => $jenisKerentanan,
                'nama' => 'Pemeliharaan tertunda',
                'deskripsi' => 'Contoh kerentanan lokal',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | STANDAR KONTROL
        |--------------------------------------------------------------------------
        */

        $standar = $this->record(
            'STANDAR_KONTROL',
            [
                'nama' => 'Standar contoh',
                'versi' => '1',
            ],
            [
                'referensi' => 'Khusus demonstrasi',
            ]
        );

        $this->record(
            'KONTROL',
            [
                'standar_id' => $standar,
                'kode' => 'K-DEMO',
            ],
            [
                'parent_id' => null,
                'nama' => 'Pemeliharaan berkala',
                'jenis' => 'KONTROL',
                'deskripsi' => 'Contoh kontrol lokal',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ASET DEMO
        |--------------------------------------------------------------------------
        */

        $this->record(
            'ASET',
            [
                'sumber_id' => $sumber,
                'external_id' => 'DEMO-1',
            ],
            [
                'unit_pemilik_id' => $satker,
                'kategori_id' => $kategori,
                'klasifikasi_informasi_id' => null,
                'kode_aset' => 'DEMO-ASET-1',
                'nama' => 'Perangkat contoh',
                'deskripsi' => 'Data lokal; bukan hasil API',
                'merk' => null,
                'nomor_seri' => null,
                'jumlah' => 1,
                'kondisi' => 'BAIK',
                'lokasi' => '',
                'tanggal_pengadaan' => null,
                'status' => 'AKTIF',
                'last_synced_at' => now(),
                'data_mentah' => '{}',
                'hash_data' => hash('sha256', '{}'),
                'perlu_ditinjau' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | PERIODE PENILAIAN
        |--------------------------------------------------------------------------
        */

        $this->record(
            'PERIODE_PENILAIAN',
            [
                'nama' => 'Periode contoh ' . now()->year,
            ],
            [
                'tanggal_mulai' => now()->startOfYear()->toDateString(),
                'tanggal_selesai' => now()->endOfYear()->toDateString(),
            ]
        );
    }

    /**
     * Membuat atau menggunakan akun yang sudah ada.
     *
     * Tidak mengganti password, email, atau username akun lama.
     */
    private function seedDemoUser(
        string $username,
        int $pegawaiId,
        string $email,
        string $password
    ): User {

        $userByUsername = User::where('username', $username)->first();

        $userByPegawai = User::where('pegawai_id', $pegawaiId)->first();

        /*
        |--------------------------------------------------------------------------
        | CEK KONFLIK AKUN
        |--------------------------------------------------------------------------
        */

        if (
            $userByUsername !== null &&
            $userByPegawai !== null &&
            $userByUsername->id !== $userByPegawai->id
        ) {
            throw new InvalidArgumentException(
                "Konflik akun: username $username dan pegawai ID $pegawaiId dimiliki akun berbeda."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | GUNAKAN AKUN LAMA
        |--------------------------------------------------------------------------
        */

        $user = $userByPegawai ?? $userByUsername;

        /*
        |--------------------------------------------------------------------------
        | BUAT AKUN JIKA BELUM ADA
        |--------------------------------------------------------------------------
        */

        if ($user === null) {

            return User::create([
                'username' => $username,
                'pegawai_id' => $pegawaiId,
                'email_login' => $email,
                'password_hash' => $password,
                'aktif' => true,
                'wajib_ganti_password' => true,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI RELASI PEGAWAI
        |--------------------------------------------------------------------------
        */

        if (
            $user->pegawai_id !== null &&
            (int) $user->pegawai_id !== $pegawaiId
        ) {
            throw new InvalidArgumentException(
                "Akun $username sudah terhubung dengan pegawai lain."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | LENGKAPI RELASI JIKA KOSONG
        |--------------------------------------------------------------------------
        */

        if ($user->pegawai_id === null) {

            $user->pegawai_id = $pegawaiId;

            $user->save();
        }

        return $user;
    }

    /**
     * Seed metode risiko 5x5.
     */
    private function seedMethod(int $versi): void
    {
        $area = $this->record(
            'AREA_DAMPAK',
            ['kode' => 'OPERASIONAL'],
            ['nama' => 'Operasional']
        );

        /*
        |--------------------------------------------------------------------------
        | TINGKAT RISIKO
        |--------------------------------------------------------------------------
        */

        $tingkat = [];

        foreach ([
            'RENDAH' => '#15803d',
            'SEDANG' => '#b45309',
            'TINGGI' => '#b91c1c',
        ] as $kode => $warna) {

            $tingkat[$kode] = $this->record(
                'TINGKAT_RISIKO',
                [
                    'versi_metode_id' => $versi,
                    'kode' => $kode,
                ],
                [
                    'nama' => ucfirst(strtolower($kode)),
                    'warna' => $warna,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SKALA KEMUNGKINAN DAN DAMPAK
        |--------------------------------------------------------------------------
        */

        $kemungkinan = [];
        $dampak = [];

        foreach (range(1, 5) as $nilai) {

            $kemungkinan[$nilai] = $this->record(
                'SKALA_KEMUNGKINAN',
                [
                    'versi_metode_id' => $versi,
                    'nilai' => $nilai,
                ],
                [
                    'label' => "Kemungkinan $nilai",
                    'label_persentase' => ($nilai * 20) . '%',
                    'label_frekuensi' => "Contoh frekuensi $nilai",
                    'kriteria' => "Contoh kriteria kemungkinan $nilai",
                ]
            );

            $dampak[$nilai] = $this->record(
                'SKALA_DAMPAK',
                [
                    'versi_metode_id' => $versi,
                    'nilai' => $nilai,
                ],
                [
                    'label' => "Dampak $nilai",
                    'kriteria' => "Contoh kriteria dampak $nilai",
                ]
            );

            foreach (['P', 'N'] as $arah) {

                $this->record(
                    'KRITERIA_DAMPAK',
                    [
                        'skala_dampak_id' => $dampak[$nilai],
                        'area_dampak_id' => $area,
                        'arah_dampak' => $arah,
                    ],
                    [
                        'deskripsi' => "Contoh dampak $arah tingkat $nilai",
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | MATRIKS RISIKO 5x5
        |--------------------------------------------------------------------------
        */

        foreach ($kemungkinan as $k => $kemungkinanId) {

            foreach ($dampak as $d => $dampakId) {

                $skor = $k * $d;

                $kodeTingkat = $skor <= 4
                    ? 'RENDAH'
                    : ($skor <= 12 ? 'SEDANG' : 'TINGGI');

                $this->record(
                    'MATRIKS_RISIKO',
                    [
                        'versi_metode_id' => $versi,
                        'kemungkinan_id' => $kemungkinanId,
                        'dampak_id' => $dampakId,
                    ],
                    [
                        'tingkat_risiko_id' => $tingkat[$kodeTingkat],
                        'skor' => $skor,
                        'rekomendasi_keputusan' => $skor <= 4
                            ? 'TERIMA'
                            : 'MITIGASI',
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | BEKUKAN METODE
        |--------------------------------------------------------------------------
        */

        DB::table('VERSI_METODE_RISIKO')
            ->where('id', $versi)
            ->update([
                'dibekukan' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Cari record berdasarkan key.
     * Jika tidak ditemukan, buat record baru.
     *
     * @param array<string, mixed> $key
     * @param array<string, mixed> $values
     */
    private function record(
        string $table,
        array $key,
        array $values
    ): int {

        $id = DB::table($table)
            ->where($key)
            ->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        return (int) DB::table($table)->insertGetId([
            ...$key,
            ...$values,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
