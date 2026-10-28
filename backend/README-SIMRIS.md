# Backend SIMRIS - Tahap 1-12

API Laravel untuk autentikasi SPA, scope unit, administrasi, metode risiko, katalog/aset, dokumen, asesmen dan residual, SOP/SOA, monitoring, audit, serta kerangka JSON Lampiran 14. Skema mengikuti ../docs/erd-v3.mmd dan perubahan brief yang disetujui. Frontend belum diintegrasikan dalam pekerjaan backend ini.

API aset lokal belum tersedia; fixture sinkron 197 baris bersifat sintetis. Templat Lampiran 14 belum jadi, sehingga ekspor berkasnya belum dibuat.

## Runtime

Dependency yang terpasang memerlukan PHP **8.4.1 atau lebih baru**, meskipun manifest aplikasi mengizinkan ^8.3. Gunakan MySQL 8 (diuji pada 8.4.3). PHP Herd yang tersedia:

```powershell
$env:Path = 'C:\Users\User\.config\herd\bin\php84;' + $env:Path
php -v
composer install
```

## Database baru

Siapkan database MySQL kosong khusus SIMRIS. Lengkapi `.env` berdasarkan `.env.example`; jangan menimpa konfigurasi yang sudah ada. Isi `APP_KEY` dengan `php artisan key:generate` hanya jika belum tersedia. Konfigurasi wajib untuk seeder:

```dotenv
SIMRIS_ADMIN_USERNAME=admin
SIMRIS_ADMIN_EMAIL=admin@example.test
SIMRIS_ADMIN_PASSWORD=
SIMRIS_DEMO_PASSWORD=
ASET_UNIT_BAWAAN_KODE=SATKER-DEMO
```

Isi password sendiri. `SIMRIS_DEMO_PASSWORD` hanya diperlukan jika `APP_ENV=local`. Tidak ada password bawaan. Semua akun yang dibuat seeder wajib mengganti password.

Setelah memastikan `DB_DATABASE` menunjuk database kosong yang benar:

```powershell
php artisan migrate --seed
```

Migrasi ini merupakan skema awal, bukan konverter database aplikasi lama. Migrasi domain menolak tabel `users`/`USERS` yang sudah ada. Jangan menggunakan `migrate:fresh` pada database berisi data pengguna. Migrasi awal Laravel disesuaikan agar tidak membuat tabel `users` yang bertabrakan dengan `USERS` ERD di MySQL Windows. Tabel infrastruktur sesi, cache, job, reset token, dan Sanctum tetap tersedia.

Seeder dasar membuat enam role, satu SUPER_ADMIN global, sumber `API_ASET`, dan kategori `BELUM_DIKATEGORIKAN`. Seeder `local` menambahkan satu satker, dua bidang dengan masing-masing satu subbidang, lima pegawai/akun operasional, metode 5×5 beku, contoh katalog/kontrol/aset, dan periode tahun berjalan. Akun demo menggunakan username `admin_satker-demo`, `kasatker-demo`, `kabid-demo`, `kasub-demo`, `staff-demo`. Data contoh aset bukan respons API sungguhan.

Seeding ulang tidak mereset password atau menimpa baris yang sudah ada. Jalankan seeder sebagai langkah persiapan tunggal, bukan secara paralel.

## Pengujian

```powershell
php artisan test --compact
php vendor/bin/pint app bootstrap/app.php routes database config tests --format agent
```

PHPUnit menggunakan SQLite in-memory secara bawaan. Untuk menguji constraint MySQL, **buat database kosong khusus tes** dan pilih namanya secara eksplisit:

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'nama_database_khusus_test'
$env:APP_ENV = 'testing'
php artisan test --compact
```

Suite skema memakai refresh database dan akan menghapus tabel dalam database tes tersebut. Variabel environment shell berlaku sampai dihapus atau terminal ditutup. Jangan menjalankan suite dengan database aplikasi sebagai target.

VIEW `v_pegawai_unit` mengembalikan satu baris per pegawai dengan nama dan ID satker/bidang/bagian/subbidang/subbagian. Pegawai tanpa penempatan utama aktif tetap tampil dengan jalur NULL. Penempatan masa depan tidak ditampilkan; generated key tetap mencadangkan satu penempatan utama terbuka sebagaimana rumus brief. Zona aplikasi Asia/Jakarta dan sesi MySQL +07:00.

Izin administrasi, pencegahan siklus unit, dan aturan penempatan tersedia pada Tahap 3. Pembekuan metode/asesmen serta syarat dokumen FINAL belum diimplementasikan dan mengikuti tahap berikutnya.

## Autentikasi SPA dan scope

1. Ambil cookie CSRF dengan `GET /sanctum/csrf-cookie`.
2. Kirim `POST /login` dengan JSON `login` (username atau email_login), `password`, dan `totp` bila diwajibkan. Aktifkan credentials pada klien HTTP serta header `X-XSRF-TOKEN` dari cookie CSRF.
3. `GET /api/me` mengembalikan `data.user`, `data.pegawai`, `data.roles` aktif, serta `data.jalur_unit`.
4. Ganti password melalui `POST /api/ganti-password` dengan `current_password`, `password`, `password_confirmation`. Password baru minimal 12 karakter dan harus berbeda. Akun `wajib_ganti_password` hanya dapat membaca identitas, mengganti password, atau logout.
5. `POST /logout` membatalkan sesi. Sesi diregenerasi saat login dan ganti password; timeout mengikuti `SESSION_LIFETIME` (default 120 menit).

Konfigurasi `FRONTEND_ORIGINS` berisi origin lengkap yang diizinkan CORS, dan `SANCTUM_STATEFUL_DOMAINS` berisi host:port frontend. Cookie autentikasi HttpOnly dan SameSite=Lax secara bawaan. Aktifkan `SESSION_SECURE_COOKIE=true` pada HTTPS produksi. Jangan mengganti APP_KEY aplikasi yang sudah berjalan secara sembarang.

Lima kegagalan login untuk kombinasi identitas/IP mengunci percobaan selama 300 detik; ada pembatas tambahan kegagalan per IP. Respons akun tidak dikenal, password salah, akun nonaktif, dan TOTP salah sengaja seragam. Gunakan cache bersama untuk deployment dengan beberapa instance.

Role aktif menggunakan rentang waktu inklusif `mulai <= sekarang <= selesai` (selesai boleh NULL). Scope unit meliputi semua turunan dan di-cache pada request saja. SUPER_ADMIN aktif dengan unit NULL mendapat scope global. Query administrasi menyaring data sebelum mengambil ID: objek luar scope menghasilkan 404. STAFF membaca dirinya sendiri dan unit penempatan yang masih berada dalam scope; peran pimpinan/admin membaca unit dan pegawai dalam scope.

## Endpoint administrasi

| Sumber daya | Kemampuan |
| --- | --- |
| `/api/users` | Daftar/detail/buat/ubah/nonaktifkan; aksi POST `{id}/aktifkan`, `{id}/nonaktifkan`, `{id}/reset-password` |
| `/api/user-role-scope` | Daftar/detail/buat/ubah/hapus scope |
| `/api/roles` | Baca saja |
| `/api/unit-organisasi` | Daftar/detail/buat/ubah/nonaktifkan; GET `pohon`, POST `{id}/pindah-induk` |
| `/api/pegawai` | Daftar/detail/buat/ubah/nonaktifkan; GET `{id}/jalur` |
| `/api/penempatan-pegawai` | Daftar/detail/buat/ubah/hapus penempatan |
| `/api/periode-penilaian` | CRUD dengan tanggal mulai/selesai |
| `/api/rencana-kerja` | CRUD kode dan uraian template |

Daftar menggunakan `data`, `links`, `meta`, serta `page`, `per_page` (1–100), `sort` (awalan `-` untuk descending), dan `filter[kolom]=nilai`. Kolom filter/urutan memakai allowlist dalam `AdminService::FILTERS`; filter saat ini berupa kecocokan nilai. SUPER_ADMIN dapat menulis seluruh administrasi. ADMIN_SATKER dapat mengelola akun, role operasional, data pegawai, dan penempatannya dalam scope; struktur unit hanya dapat diubah SUPER_ADMIN. Role biasa tidak dapat membaca akun dan role scope; seluruh role aktif dapat membaca roles, periode, serta rencana kerja.

Sebelum mengubah role scope atau melakukan reset password, panggil `POST /api/konfirmasi-password` dengan `password` dan cookie sesi yang sama. Konfirmasi berlaku 300 detik. Reset password menerima `password` dan `password_confirmation`; hasilnya mewajibkan pengguna mengganti password dan tidak mengembalikan nilai password/hash.

Aturan R1–R4, siklus dan level unit, larangan nonaktif unit dengan anak/pegawai aktif, serta penutupan penempatan utama lama dijalankan dalam layanan. Mutasi administrasi menggunakan transaksi dan penguncian untuk memeriksa ulang hak akses serta mempertahankan minimal satu SUPER_ADMIN aktif. Akun sendiri tidak dapat dinonaktifkan atau dicabut role super adminnya. DELETE untuk user, unit, dan pegawai adalah penonaktifan, bukan hapus fisik. Kesalahan bisnis mengembalikan 409 dengan `code` stabil.

## Audit dan hak database

Trait `Auditable` membungkus penyimpanan/penghapusan model dalam transaksi yang sama dengan log. Login berhasil/gagal, logout, perubahan password, reset password, dan perubahan role memiliki aksi tersendiri. Password, hash, token, secret, cookie, isi file, serta field sensitif bersarang dibuang dari snapshot audit. Jika penulisan audit gagal, perubahan model dibatalkan.

`LogAudit` menolak update/delete melalui model dan belum memiliki endpoint publik; endpoint baca audit dijadwalkan Tahap 11. Query Builder/SQL langsung tetap tunduk pada hak database. Pada produksi gunakan **akun aplikasi terpisah dari akun migrasi**, tanpa privilege global/database-level yang memberi UPDATE/DELETE ke semua tabel. Berikan hak per tabel:

```sql
-- Contoh, sesuaikan nama database dan akun aplikasi yang telah dibuat.
GRANT SELECT, INSERT ON simris.LOG_AUDIT TO 'simris_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON simris.USERS TO 'simris_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON simris.UNIT_ORGANISASI TO 'simris_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON simris.PEGAWAI TO 'simris_app'@'localhost';
-- Berikan DML yang diperlukan pada setiap tabel lain secara eksplisit.
-- VIEW memerlukan SELECT; DDL hanya untuk akun migrasi.
SHOW GRANTS FOR 'simris_app'@'localhost';
```

Jangan memberi `ALL ON simris.*` lalu menganggap grant khusus LOG_AUDIT di atas akan membatasinya; privilege MySQL bersifat akumulatif. Grant produksi tidak diterapkan otomatis pada akun/database lokal yang sudah ada.

## TOTP opsional

`SUPER_ADMIN_TOTP_ENABLED=true` mewajibkan kode enam digit pada login SUPER_ADMIN. Provisioning dilakukan di luar API: simpan peta ID pengguna ke secret Base32 dalam `SUPER_ADMIN_TOTP_SECRETS`, melalui konfigurasi privat/secret manager. Contoh bentuk tanpa secret nyata: `{"123":"SECRET_BASE32_PER_USER"}`. Secret tidak masuk skema ERD, respons API, atau audit. Jika fitur diaktifkan tanpa secret yang valid, login SUPER_ADMIN ditolak. Jendela verifikasi ±1 interval 30 detik dan replay dicegah dengan cache atomik bersama. Antarmuka enrollment bukan bagian tahap ini.

## OpenAPI

Scramble menyediakan `/docs/api.json` dan `/docs/api`. Akses bebas hanya pada `APP_ENV=local`; di luar local memerlukan sesi SUPER_ADMIN aktif. Ekspor dari database yang telah dimigrasikan:

```powershell
php artisan scramble:export --path=storage/app/openapi-stage3.json
```

Endpoint autentikasi dan administrasi tercakup. Resource administrasi digunakan oleh beberapa model; Scramble saat ini memberi satu peringatan inferensi `JR001` untuk `AdminResource`. Dokumen tetap terbit, tetapi tipe respons tiap sumber daya belum presisi untuk mengganti tipe frontend secara otomatis. Penyempurnaan anotasi lengkap berada pada Tahap 12; peringatan ini tidak boleh dianggap kontrak frontend final.


## Tahap 4: metode risiko

CRUD tersedia di /api/versi-metode-risiko, /api/skala-kemungkinan, /api/skala-dampak, /api/area-dampak, /api/kriteria-dampak, /api/tingkat-risiko, /api/matriks-risiko. Pola GET daftar/detail, POST buat, PUT/PATCH ubah, DELETE hapus. Semua enam role aktif dapat membaca; hanya SUPER_ADMIN dapat menulis. Daftar mendukung page, per_page (1?100), sort (awalan - untuk turun), dan filter[kolom]=nilai dengan allowlist kolom. Response memakai data/links/meta dan waktu ISO 8601 Asia/Jakarta.

Alur penyiapan:

1. POST /api/versi-metode-risiko dengan nama, versi, referensi. Status awal draft; dibekukan tidak boleh dikirim.
2. Isi area global (kode, nama), skala kemungkinan (versi_metode_id, nilai, label, label_persentase, label_frekuensi, kriteria), skala dampak (versi_metode_id, nilai, label, kriteria). Nilai masing-masing skala harus ditambahkan berurutan dari 1.
3. Isi tingkat risiko (versi_metode_id, kode, nama, warna format #RRGGBB) dan kriteria (skala_dampak_id, area_dampak_id, arah_dampak P/N, deskripsi).
4. POST /api/matriks-risiko/generate dengan versi_metode_id, tingkat_risiko_id, rekomendasi_keputusan. Sel yang belum ada dibuat dengan pilihan tersebut. Ubah tingkat/rekomendasi tiap sel sesuai metode; generate berikutnya tidak menimpa sel lama. Skor selalu dihitung server, tidak boleh dikirim. CRUD sel menerima versi_metode_id, kemungkinan_id, dampak_id, tingkat_risiko_id, rekomendasi_keputusan.
5. POST /api/versi-metode-risiko/{id}/validasi. Respons 200 berisi data.valid dan data.masalah (daftar masalah); tidak mengubah data.
6. Konfirmasi password melalui endpoint yang sudah ada, lalu POST /api/versi-metode-risiko/{id}/bekukan. Metode belum lengkap menghasilkan 409 metode_belum_valid beserta masalah. Setelah berhasil, versi/anak dan area global yang dirujuk tidak dapat diedit/dihapus (409 versi_sudah_beku).
7. Untuk perubahan metode beku, POST /api/versi-metode-risiko/{id}/duplikasi dengan versi baru, serta nama/referensi opsional. Respons 201 berisi versi draft baru. Semua ID anak dipetakan ulang; area tetap global.

Setiap pasangan skala dampak ? area wajib memiliki setidaknya satu kriteria P atau N. Area global baru berlaku untuk kelengkapan draft berikutnya, tidak mengubah cakupan metode yang sudah beku. Hapus draft beserta anak berlangsung atomik, tetapi versi yang dirujuk asesmen ditolak. Guard forAssessment() sudah tersedia untuk integrasi Tahap 7; endpoint asesmen belum dibuat.

Tes khusus: php artisan test --compact tests/Feature/MetodeRisikoTest.php. Seluruh suite: php artisan test --compact. Ekspor tahap ini: storage/app/openapi-stage4.json. Resource metode memiliki anotasi tipe field; pemisahan schema per sumber daya dan penajaman response aksi tetap perlu pada Tahap 12. Scramble masih memberi JR001 untuk resource administrasi dan metode yang dipakai bersama oleh beberapa model.


## Tahap 5: katalog dan aset

Master CRUD: jenis-ancaman, sumber-ancaman, ancaman, jenis-kerentanan, kerentanan, standar-kontrol, kontrol, regulasi, sumber-aset, kategori-aset, klasifikasi-informasi. Semua role aktif membaca, SUPER_ADMIN menulis. GET kontrol/pohon membaca hierarki. Ancaman menerima sumber_ids untuk tautan sumber. POST {ancaman|kerentanan|kontrol}/impor menerima multipart file CSV; GET endpoint /ekspor mengunduh CSV. Header kolom mengikuti ERD, bukan label tampilan.

GET aset dan aset/{id} menampilkan aset dalam scope. PATCH aset/{id} hanya menerima unit_pemilik_id, kategori_id, klasifikasi_informasi_id, lokasi, deskripsi dari STAFF dalam scope. POST aset/tetapkan menerima ids (maksimum 100), unit_pemilik_id, kategori_id; kategori tujuan harus sudah dikategorikan. Hasil melaporkan berhasil/gagal dan mengosongkan perlu_ditinjau pada baris berhasil. Tidak ada POST/DELETE aset. Penugasan-aset memakai CRUD terpisah oleh STAFF dalam scope.

Daftar aset menerima q, filter[sumber_id], filter[kategori_id], filter[unit_pemilik_id], filter[kondisi], filter[status], filter[perlu_ditinjau], filter[tahun_pengadaan], page/per_page/sort. Raw payload dan hash tidak diekspos pada resource aset.

SUPER_ADMIN dapat GET aset-sinkron, GET aset-sinkron/{id}, POST aset-sinkron/jalankan (202). Gunakan sort=-id dan filter[sumber_id] untuk riwayat terbaru. Pengulangan saat antrean/eksekusi aktif menghasilkan 409 sinkron_sedang_berjalan.

Konfigurasi lokal yang harus diisi saat API tersedia: ASET_API_URL (URL lengkap), ASET_API_TOKEN (opsional), ASET_UNIT_BAWAAN_KODE (satker aktif). ASET_SINKRON_AKTIF default false, ASET_SINKRON_JADWAL default 02:00 Asia/Jakarta. Driver belum diuji terhadap API lokal nyata karena pengguna menyatakan API belum ada; fixture 197 baris adalah sintetis.

Jalankan php artisan queue:work --timeout=900 --tries=1 serta php artisan schedule:work untuk lingkungan pengembangan ketika konfigurasi siap. Untuk database queue, DB_QUEUE_RETRY_AFTER minimal 1200; bila mengganti driver antrean, sesuaikan retry_after lebih besar dari timeout. Jangan gunakan cache array untuk lock lintas proses; gunakan database/Redis bersama pada runtime. Tidak ada worker/server yang dinyalakan otomatis oleh implementasi.


## Tahap 6: dokumen dan lampiran

GET/POST /api/dokumen; GET /api/dokumen/{id}; GET/POST /api/dokumen/{id}/versi. Dokumen baru menerima unit_pemilik_id, kode, judul, jenis (SOP/BUKTI/LAPORAN/PENDUKUNG). Versi baru menerima isi_teks opsional dan format_teks PLAIN/MARKDOWN/RICH_TEXT_JSON; nomor versi, pembuat, waktu, serta status diisi server.

GET /api/dokumen-versi dan /api/dokumen-versi/{id}; PATCH versi hanya untuk draft; POST /api/dokumen-versi/{id}/finalkan membekukan teks dan daftar lampiran. FINAL harus berisi teks atau minimal satu file AMAN, dan tidak boleh memiliki file PENDING/DITOLAK.

POST /api/file-lampiran memakai multipart dokumen_versi_id, file, keterangan opsional, urutan opsional. Metadata awal PENDING; jalankan queue:work agar PeriksaFileLampiran memeriksa berkas. GET /api/file-lampiran serta /{id} membaca metadata dalam scope; GET /{id}/unduh mengunduh berkas AMAN. DELETE /api/dokumen-versi/{id}/file/{file} melepas lampiran dari draft; tidak tersedia penghapusan file yang masih dirujuk.

Disk lampiran disimpan di storage/app/lampiran-private tanpa akses publik. Ekstensi/MIME dan batas ukuran di config/lampiran.php (LAMPIRAN_MAX_KB default 10240). PemindaiBerkasDasar dapat diganti binding PemindaiBerkas ke ClamAV ketika tersedia. Jangan menganggap pemeriksaan MIME sebagai antivirus.


## Tahap 7: asesmen inti

GET/POST /api/asesmen, GET/PATCH /api/asesmen/{id}. Buat menerima unit_id, periode_id, versi_metode_id, tujuan, ruang_lingkup. Metode wajib beku dan periode berjalan; seri/nomor/status/pembuat diisi server. PATCH hanya tujuan/ruang_lingkup saat draft.

GET/POST /api/asesmen/{id}/risiko; GET/PATCH/DELETE /api/risiko/{id}. Payload: ancaman_id, kerentanan_id, area_dampak_id, pemilik_risiko_id, kode, skenario, uraian_kerentanan, uraian_dampak, arah_dampak P/N; asesmen_aset_ids opsional menautkan snapshot dari asesmen yang sama.

GET/POST /api/asesmen/{id}/aset merekam aset_id, nilai_kekritisan, dasar_kekritisan. GET/PATCH/DELETE /api/asesmen-aset/{id}; PATCH hanya nilai/dasar kekritisan. Snapshot nama, kategori, klasifikasi, jalur unit dan lokasi tidak dapat diedit.

GET/POST /api/risiko/{id}/penilaian menerima matriks_id, tahap SAAT_INI/TARGET_SETELAH, alasan_kemungkinan, alasan_dampak, kesimpulan. Matriks harus satu metode dengan asesmen. POST ulang tahap yang sama memperbarui penilaian awal selama draft.

GET/POST /api/risiko/{id}/usulan-keputusan menerima keputusan MITIGASI/TERIMA/HINDARI/ALIHKAN dan alasan; terikat ke penilaian SAAT_INI. Detail penilaian/usulan dapat dibaca melalui /api/penilaian/{id} dan /api/usulan-keputusan/{id}. Semua pembacaan memeriksa scope sebelum menemukan ID.


## Tahap 8: pengajuan dan review awal

POST /api/asesmen/{id}/ajukan memerlukan sedikitnya satu risiko, penilaian SAAT_INI dan TARGET_SETELAH serta usulan pada setiap risiko. POST /api/asesmen/{id}/review menerima tahap KASUB_AWAL/KABID_AWAL, keputusan SETUJU/TOLAK, catatan (wajib saat TOLAK). Reviewer harus memegang role pada tingkat unit dan cabang yang tepat, serta lolos pemisahan tugas.

Kasub setuju meneruskan ke Kabid; Kabid setuju membekukan bagian awal dan menuju PENANGANAN jika ada MITIGASI, atau MENUNGGU_KASATKER untuk risiko lain. TOLAK awal membekukan seluruh versi sebagai DITOLAK. POST /api/asesmen/{id}/buat-revisi membuat draft baru dengan ID anak baru dan versi lama tetap utuh. GET /api/asesmen/{id}/riwayat-versi dan GET /api/asesmen/{id}/review tersedia dalam scope dengan pagination.

Aturan transisi di config/alur_asesmen.php; konfigurasi skip dan jenis unit reviewer di config/alur_review.php. Tidak ada endpoint ubah status langsung.


### Tahap 9
Alur lanjutan: POST asesmen/{id}/rencana-penanganan, rencana-penanganan/{id}/progres, progres/{id}/bukti, asesmen/{id}/selesai-penanganan, risiko/{id}/penilaian (AKTUAL_SETELAH), penilaian/{id}/bukti, asesmen/{id}/ajukan-residual, asesmen/{id}/review. Semua berawalan /api. Pengesahan akhir membutuhkan konfirmasi password pada session SPA yang sama. Riwayat aktual/progres/keputusan tidak dapat ditimpa. Penolakan lanjutan membutuhkan kembali_ke PENANGANAN atau RESIDUAL_DRAFT.


### Tahap 10
CRUD draft: /api/kontrol-internal, /api/sop, /api/soa. Buat versi: POST sop/{id}/versi atau soa/{id}/versi. Isi SOA: POST soa-versi/{id}/detail dan soa-detail/{id}/benchmark. Pengajuan: POST sop-versi/{id}/ajukan atau soa-versi/{id}/ajukan; review menerima tahap KASUB/KABID/KASATKER, keputusan SETUJU/TOLAK, catatan. Riwayat dibaca lewat sop-review/soa-review dengan filter ID versi.

Mutasi draft, pengajuan, penghapusan, dokumen, lampiran, aset, dan penugasan aset dilakukan oleh STAFF dalam scope. ADMIN_SATKER hanya mengelola akun, role operasional, data pegawai, dan penempatan.

Penautan menggunakan GET/POST/DELETE: kontrol-internal/{id}/standar atau /sop; sop-versi/{id}/kontrol; soa-detail/{id}/sop, /risiko, /bukti; risiko/{id}/kontrol, /kontrol-internal, /sop; rencana-penanganan/{id}/sop. Payload memakai nama FK ERD. Tautan risiko mengikuti pembekuan awal asesmen; tautan penanganan hanya pada status PENANGANAN.


### Tahap 11
GET /api/monitoring/{asesmen|soa|sop|dokumen|file-lampiran} dan /{id} adalah baca saja, dengan scope unit. GET /api/log-audit dan /api/log-audit/ekspor hanya SUPER_ADMIN; filter meliputi user_id, entitas, entitas_id, aksi, waktu. Tidak ada endpoint ubah/hapus audit.

GET /api/laporan/lampiran-14?asesmen_id=ID mengembalikan data risiko berhalaman, penilaian, usulan/keputusan dan rencana penanganan, serta metadata asesmen. Pengguna menyatakan templat belum jadi; ekspor berkas belum dibuat.


## Menjalankan backend dan kontrak API

Setelah dependency, .env, dan migrasi siap, gunakan terminal backend:

~~~powershell
php artisan serve --host=127.0.0.1 --port=8000
~~~

Di terminal terpisah, jalankan antrean dan scheduler sesuai kebutuhan konfigurasi:

~~~powershell
php artisan queue:work --timeout=900 --tries=1
php artisan schedule:work
~~~

Scheduler sinkron aset hanya aktif bila ASET_SINKRON_ENABLED=true dan URL/default unit sudah benar. Jalankan satu scheduler terkelola pada deployment. Simpan unggahan pada storage/app/lampiran-private; jangan membuat tautan publik ke folder tersebut. Pemindai bawaan memeriksa MIME/ekstensi/checksum, bukan antivirus.

OpenAPI tersedia di /docs/api.json. Di luar local, akses dokumentasi membutuhkan akun SUPER_ADMIN. Scramble memakai aturan Form Request dengan default resource route yang sesuai; skema respons memakai kolom database, cast dan hidden model, serta tambahan API Resource. Jalankan migrasi sebelum menghasilkan kontrak:

~~~powershell
php -d memory_limit=512M artisan scramble:export --path=storage/app/openapi.json --fail-on-unknown
~~~

Cookie session Sanctum adalah autentikasi operasional. Ambil /sanctum/csrf-cookie, kirim cookie dan X-XSRF-TOKEN pada login/mutasi, dan gunakan session yang sama untuk /api/konfirmasi-password. Jangan memakai token sinkron aset sebagai token login pengguna.

Pengujian memakai batas memori 512 MB di phpunit.xml. Jalankan Pint sampai selesai sebelum memulai tes/OpenAPI; parser memakai nomor baris dari refleksi PHP. Folder kerja ini belum menjadi repositori Git, sehingga --dirty tidak tersedia; gunakan daftar direktori pada bagian Pengujian.

### Izin database dan operasi

Gunakan akun migrasi terpisah dari akun aplikasi. Akun aplikasi hanya diberi SELECT dan INSERT pada LOG_AUDIT; jangan memberikan UPDATE, DELETE, DROP atau hak global yang mengalahkan pembatasan per tabel. Berikan hak transaksi yang diperlukan pada tabel domain dan infrastruktur antrean/session/cache secara eksplisit. Contoh GRANT di atas adalah pola, bukan skrip provisioning lengkap. Pemeriksaan izin dilakukan administrator database lewat SHOW GRANTS; backend tidak mengubah grant database otomatis.

### Batas hasil

- URL API aset, autentikasi sumber, dan respons asli masih perlu disediakan sebelum uji integrasi nyata.
- Lampiran 14 hanya kerangka JSON sampai templat resmi tersedia.
- Route tambahan /api/items dan ItemController berada di luar brief SIMRIS; controller tersebut merujuk App\Models\Item yang belum tersedia. Tidak dibuat tabel/model tambahan untuk route itu.

Kontrak OpenAPI SIMRIS mengecualikan /api/items sampai modul tambahan tersebut memiliki model dan kontrak yang disepakati. Endpoint runtime tetap dipertahankan. Enum dan regex tekstual diberi tipe string eksplisit pada ekstraksi dokumentasi.

Verifikasi akhir 14 Oktober 2026: 696 tes / 4.119 assertion lulus di SQLite dan MySQL; Pint bersih; OpenAPI --fail-on-unknown serta generasi openapi-typescript lulus. Artefak tersedia di storage/app/openapi.json dan storage/app/openapi.d.ts.
