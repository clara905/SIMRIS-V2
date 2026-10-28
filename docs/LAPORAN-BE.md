# Laporan backend SIMRIS

Status terkini (14 Oktober 2026): implementasi SIMRIS sampai Tahap 12 selesai. Seluruh 696 tes lulus di SQLite dan MySQL (4.119 assertion per suite), Pint bersih, dan OpenAPI lolos pemeriksaan tipe ketat. Integrasi API aset asli dan ekspor berkas Lampiran 14 menunggu input yang belum tersedia.

## Tahap 1 — Skema (12 Oktober 2026)

Status saat laporan Tahap 1: selesai dan teruji. Tahap 2 saat itu belum dimulai.

### Hasil

- ERD pengguna disimpan sebagai `erd-v3.mmd` dan menjadi sumber skema.
- Migrasi 67 tabel ERD ditambah `SINKRON_ASET` menghasilkan 68 tabel domain, FK termasuk relasi gabungan, indeks unik, timestamp, dan perubahan yang disetujui brief.
- Penempatan memiliki `utama` dan generated unique key `kunci_utama_aktif`; satu pegawai tidak dapat memiliki dua penempatan utama terbuka.
- VIEW `v_pegawai_unit` menghasilkan nama dan ID setiap level organisasi dari penempatan utama aktif.
- Model/factory dasar: User, Role, UserRoleScope, UnitOrganisasi, Pegawai, PenempatanPegawai. User memakai kolom kredensial ERD dan menyembunyikan hash password.
- Seeder dasar berisi enam role, SUPER_ADMIN dari environment yang wajib ganti password, sumber aset, dan kategori bawaan.
- Seeder local berisi organisasi, pegawai/akun operasional untuk tiap role, metode 5×5 lengkap dan beku, katalog, kontrol, aset contoh, dan periode. Seeding ulang tidak menimpa password/data yang sudah ada.
- Zona aplikasi Asia/Jakarta; zona sesi MySQL +07:00. `.env.example` disiapkan untuk MySQL dan kredensial seeder tanpa password bawaan.
- PHP Herd 8.4.26 digunakan tanpa instalasi runtime baru. Boost 2.10.3 dipasang sesuai aturan bootstrap dan panduannya dibaca kembali.

### Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| Seluruh suite, SQLite in-memory | 26 tes lulus, 150 assertion |
| Seluruh suite, MySQL 8.4.3 | 26 tes lulus, 150 assertion |
| Migrasi bersih MySQL | Lulus |
| Rollback migrasi domain kemudian migrasi ulang | Lulus |
| Seeder local melalui Artisan | Lulus |
| Pint pada file PHP Tahap 1 | Lulus |

Tes membuktikan seluruh tabel hadir, keunikan penempatan, pelepasan kunci saat penempatan ditutup, jalur organisasi kedua cabang hierarki, pengecualian penempatan masa depan, idempotensi seeder, rollback seeder saat kredensial kosong, hash tidak terekspos, metode 25 sel, keunikan business key, serta perlindungan referensi pegawai/dokumen/file dan relasi gabungan risiko-kontrol-SOP.

Seluruh operasi DDL dilakukan pada database baru `simris_v3_codex_20261012_143505_test`. Database aplikasi yang sudah ada tidak diubah. Database tes dipertahankan untuk pemeriksaan; kredensial seeding manual menggunakan nilai acak khusus tes dan tidak disimpan ke dokumen.

Pint `--dirty` tidak dapat dipakai karena workspace belum memiliki Git; format diperiksa langsung pada direktori/file yang diubah. Tidak dibuat commit yang mengklaim keadaan awal sebelum perubahan.

### Asumsi dan batas

- Rekonsiliasi lengkap tersedia pada `ASUMSI.md`. Tidak ada usulan tambahan skema domain di luar persetujuan brief.
- Periode berjalan diturunkan dari rentang tanggal; AREA_DAMPAK tetap global; VARCHAR tanpa panjang dipetakan ke 255 dan nilai kekritisan DECIMAL(15,4).
- Migrasi adalah skema baru, bukan konversi database lama. Tabel users lama ditolak secara eksplisit untuk mencegah benturan dengan USERS pada MySQL Windows.
- Kebijakan akses, autentikasi SPA, audit otomatis, status FINAL, pembekuan, dan workflow belum diimplementasikan. Itu merupakan pekerjaan tahap selanjutnya. Frontend masih memakai mock.
- Cara menjalankan dan menguji tersedia di `../backend/README-SIMRIS.md`.

### Keputusan berikutnya

Persetujuan untuk Tahap 2: autentikasi Sanctum, scope unit, Policy dasar, dan audit. Jeda ini mengikuti instruksi pengguna pada bagian 14 brief: “Di akhir tiap tahap: jalankan tes dan Pint, ringkas hasilnya, lalu berhenti dan tunggu persetujuan saya sebelum lanjut.”

## Tahap 2–3 — Autentikasi dan administrasi (13 Oktober 2026)

Status: implementasi Tahap 2 dan Tahap 3 selesai dan teruji. Pengguna secara eksplisit memilih “Kerjakan Tahap 2 lalu Tahap 3”. Tahap 4 belum dimulai.

### Tahap 2

- Autentikasi cookie Sanctum: CSRF cookie, login username/email, logout, identitas `me`, ganti password, dan konfirmasi ulang password.
- Regenerasi/invalidation sesi, pembatasan login dan penguncian sementara, penolakan akun nonaktif, wajib ganti password, CORS origin terdaftar, serta header keamanan dasar.
- `ScopeUnit` membaca role aktif beserta keturunannya dan menyimpan cache hanya sepanjang request; role SUPER_ADMIN global ditangani terpisah.
- Policy dasar administrasi dan audit, dengan query scope sebelum pencarian ID agar data luar unit mengembalikan 404.
- `Auditable` dan `AuditService` mencatat perubahan dalam transaksi yang sama; login, perubahan/reset password, perubahan role, dan logout punya aksi tersendiri. Snapshot sensitif disaring rekursif. Model audit menolak perubahan/penghapusan.
- TOTP opsional SUPER_ADMIN melalui konfigurasi privat per pengguna, jendela waktu terbatas, dan cache untuk menolak replay. Tidak ada kolom atau UI enrollment tambahan.

### Tahap 3

- Administrasi users, user-role-scope, roles baca, unit organisasi, pegawai, penempatan, periode penilaian, dan rencana kerja.
- Aksi reset/aktif/nonaktif akun, pohon unit, pindah induk, serta jalur pegawai.
- R1: scope global hanya SUPER_ADMIN dan wajib global untuk role tersebut.
- R2–R3: minimal satu SUPER_ADMIN aktif dipertahankan; akun/role sendiri tidak dapat dinonaktifkan atau dicabut. Pemeriksaan dan mutasi dijalankan di dalam transaksi dengan lock dan otorisasi ulang.
- R4: rentang waktu valid serta pencegahan scope role/unit yang tumpang tindih.
- R5–R6 untuk unit: siklus ditolak, level mengikuti `config/aturan_unit.php`. KONTROL/KATEGORI_ASET masih mengikuti Tahap 5.
- R7: user/unit/pegawai tidak dihapus fisik melalui API/model; unit dengan anak atau pegawai aktif tidak bisa dinonaktifkan.
- R8: penempatan utama baru menutup penempatan sebelumnya pada sehari sebelum tanggal mulai, secara atomik.
- Daftar menggunakan pagination maksimum 100, allowlist sort/filter, API Resource, serta galat validasi/bisnis berbahasa Indonesia dengan kode 409 stabil.
- Penulisan hanya SUPER_ADMIN; matriks baca mengikuti brief. Tidak ada perubahan skema domain, endpoint frontend, atau keputusan arsitektur tahap sebelumnya.

### Hasil pemeriksaan

| Pemeriksaan | Hasil |
| --- | --- |
| Seluruh suite SQLite in-memory | 244 tes lulus, 670 assertion |
| Seluruh suite MySQL 8.4.3 | 244 tes lulus, 670 assertion |
| Laravel Pint pada kode Tahap 2–3 | Lulus |
| Route API | Endpoint Tahap 2–3 terdaftar |
| `/docs/api.json` | Lulus tes HTTP; akses luar local dibatasi SUPER_ADMIN |
| Ekspor Scramble | Berhasil; satu peringatan JR001 pada AdminResource |

Suite mencakup matriks baca dan penulisan per role, penolakan akses lintas unit melalui ID/list, perlindungan akun sendiri, pencabutan grant pengguna lain, reset password dengan konfirmasi kedaluwarsa/baru, CSRF, CORS, penguncian login, hash tersembunyi, TOTP/replay, audit rollback, injeksi sort/filter, hierarki, serta penempatan. Regresi Tahap 1 tetap lulus.

Pengujian MySQL tetap memakai `simris_v3_codex_20261012_143505_test`. Tidak dilakukan migrasi atau perubahan privilege terhadap database aplikasi yang sudah ada. Tidak ada server baru yang ditinggalkan berjalan oleh tugas ini.

Pada pemeriksaan penutup 13 Oktober, pengulangan ekspor OpenAPI ke MySQL gagal karena port 3306 menolak koneksi (layanan lokal tidak aktif). Ini terjadi setelah suite MySQL 244 tes lulus. Ekspor terakhir kemudian berhasil memakai SQLite sementara dengan seluruh migrasi; berkas hasil berada di `backend/storage/app/openapi-stage3.json`. Tidak diklaim bahwa MySQL masih berjalan pada akhir tugas. Database SQLite sementara tersebut dibuang setelah verifikasi.

Pint `--dirty` tetap tidak tersedia karena workspace belum menggunakan Git; pemeriksaan format dilakukan langsung pada kode sumber yang terkait. Referensi pengujian: [dokumentasi HTTP tests Laravel 13](https://laravel.com/framework/docs/13.x/http-tests).

### Asumsi dan batas yang perlu diketahui

- Detail keputusan implementasi berada di `ASUMSI.md`; panduan login SPA, administrasi, TOTP, dan grant database berada di `../backend/README-SIMRIS.md`.
- Runtime produksi tetap membutuhkan APP_KEY, konfigurasi origin/cookie, kredensial seed, serta akun database aplikasi dengan hak terbatas. Grant produksi hanya didokumentasikan; akun database lokal pengguna tidak diubah.
- Frontend masih memakai mock. OpenAPI terbit, tetapi resource administrasi bersama menghasilkan peringatan JR001 sehingga tipe respons per sumber daya belum presisi untuk regenerasi frontend final. Penyempurnaan anotasi lengkap tetap berada pada Tahap 12.
- Tahap 4–12 belum diimplementasikan. Tidak ada klaim bahwa pembekuan metode, workflow asesmen, sinkron aset, dokumen FINAL, monitoring, atau endpoint log audit telah selesai.

### Persetujuan berikutnya

Berhenti setelah Tahap 3 sesuai instruksi pengguna. Menunggu persetujuan Tahap 4: versi metode risiko, skala, area, kriteria, tingkat, matriks, generate, validasi, pembekuan, dan duplikasi.


## Tahap 4 ? Metode risiko (13 Oktober 2026)

Status: implementasi Tahap 4 selesai setelah persetujuan pengguna ?okay lanjut ke tahap 4?. Catatan Tahap 2?3 di atas adalah riwayat; status terbaru mengikuti bagian ini. Tidak ada perubahan skema domain, dependensi, frontend, atau perluasan ke Tahap 5.

### Implementasi

- CRUD versi metode risiko, skala kemungkinan/dampak, area dampak, kriteria dampak, tingkat risiko, dan matriks risiko; pagination, filter/sort allowlist, Form Request, API Resource, serta Policy.
- Seluruh role aktif dapat membaca master global dan memanggil validasi. Mutasi hanya SUPER_ADMIN.
- R9: skala unik dan berurutan dari 1 pada setiap mutasi. R10: referensi skala/tingkat harus satu versi, skor dihitung server, satu sel per pasangan; kelengkapan seluruh matriks diperiksa sebelum pembekuan.
- Generate hanya mengisi pasangan yang belum ada, memakai tingkat dan rekomendasi yang dipilih admin. Tidak menebak ambang tingkat atau menimpa keputusan sel lama.
- R11: validasi mengumpulkan masalah skala, matriks, tingkat, serta kriteria setiap dampak ? area. Bekukan membutuhkan konfirmasi password baru dan validasi lengkap.
- Observer menjaga versi/semua anak beku, termasuk model stale. Area global yang digunakan versi beku dilindungi; area baru berlaku untuk kelengkapan draft berikutnya.
- Duplikasi membuat draft beserta seluruh anak, memetakan ulang ID skala/tingkat/matriks/kriteria, dan mempertahankan versi sumber serta area global.
- Hapus draft beserta anaknya atomik; versi yang dirujuk asesmen tidak dapat dihapus. Foreign key tetap melindungi referensi lain.
- R12: guard forAssessment() menolak draft dan menerima versi beku di dalam transaksi. Integrasi pada endpoint/model asesmen dilakukan saat Tahap 7; endpoint tersebut belum diimplementasikan pada tahap ini.
- Semua mutasi memakai transaksi dan audit. Kegagalan audit di tengah pembaruan/duplikasi membatalkan perubahan beserta audit yang sempat dibuat.

### Hasil pemeriksaan

| Pemeriksaan | Hasil |
| --- | --- |
| Seluruh suite SQLite in-memory | **379 tes lulus, 1.379 assertion** |
| Seluruh suite MySQL | **379 tes lulus, 1.379 assertion** |
| Tambahan Tahap 4 | 135 tes fitur, termasuk pengujian OpenAPI |
| Laravel Pint | Lulus pada direktori sumber terkait |
| Route API | CRUD dan seluruh aksi Tahap 4 terdaftar |
| Ekspor OpenAPI | storage/app/openapi-stage4.json; dua peringatan JR001 pada resource bersama |

Pengujian mencakup matriks akses enam role, autentikasi, CRUD, pembekuan semua endpoint anak, konfirmasi password kedaluwarsa, kelengkapan dan skor matriks, penolakan lintas versi, urutan skala, generate berulang, pemetaan ulang duplikasi, penjagaan referensi asesmen, filter/sort, audit atomik, serta regresi Tahap 1?3.

Tes pertama OpenAPI salah mengharapkan path tanpa prefix /api; telah diperbaiki sesuai konfigurasi dan seluruh suite dijalankan ulang hingga lulus. MySQL tersedia saat suite akhir dijalankan (pengamatan awal port tidak membuktikan layanan berhenti). Pengujian tetap menggunakan database terpisah simris_v3_codex_20261012_143505_test, tidak memigrasikan database aplikasi. Tidak ada layanan yang dimulai oleh pekerjaan ini. SQLite sementara untuk ekspor awal sudah dihapus.

Pint --dirty telah dicoba, tetapi workspace bukan repository Git, sehingga formatter dijalankan langsung pada sumber terkait. Anotasi respons metode dan galat tersedia; resource generik masih menghasilkan JR001, dan pemisahan schema per endpoint serta penajaman response aksi tetap dicatat untuk Tahap 12. Ekspor tidak diklaim sebagai kontrak frontend final. Rujukan assertion: [Laravel HTTP Tests](https://laravel.com/framework/docs/13.x/http-tests).

Detail kontrak dan keputusan tanpa perubahan skema tersedia di ASUMSI.md serta backend/README-SIMRIS.md.

### Batas tahap

Berhenti setelah Tahap 4 sesuai instruksi pengguna. Tahap 5 (katalog, standar, aset master, impor CSV, sinkronisasi aset) belum dikerjakan dan menunggu persetujuan pengguna.


## Tahap 5 - Katalog, standar, dan aset (13 Oktober 2026)

Selesai: CRUD katalog/master, pohon dan pencegahan siklus kontrol/kategori, tautan sumber ancaman, impor/ekspor CSV, scope aset, penetapan massal dan penugasan, driver API aset, normalisasi/idempotensi, riwayat, job antrean, lock atomik, scheduler konfigurabel dan audit.

Hasil: seluruh suite SQLite dan MySQL masing-masing **512 tes lulus, 2.145 assertion**. Tambahan Tahap 5: 133 tes. Laravel Pint lulus; --dirty tidak tersedia karena workspace bukan Git. Database pengujian terpisah tetap digunakan.

Asumsi: fixture 197 baris sintetis berdasarkan brief karena pengguna menjawab API lokal belum ada. Riwayat/pemicu sinkron khusus SUPER_ADMIN, mutasi aset hanya STAFF pada scope role tersebut. ADMIN_SATKER hanya mengelola akun dan data pegawai. Tidak ada perubahan skema. Detail berada di ASUMSI.md dan README backend.

Keputusan/kebutuhan pengguna: nanti sediakan URL API lokal, metode autentikasi, dan respons asli untuk uji integrasi. Driver belum diklaim terhubung ke sistem aset nyata. Ini tidak menghalangi pengujian kontrak dengan Http::fake atau implementasi modul berikutnya.

Persetujuan pengguna untuk melanjutkan sampai Tahap 12 sudah berlaku; lanjut Tahap 6 setelah pemeriksaan di atas lulus.


## Tahap 6 - Dokumen dan lampiran (13 Oktober 2026)

Selesai: dokumen dalam scope, versi draft bernomor otomatis, finalisasi, observer FINAL, upload privat dengan nama acak/checksum/MIME, job PemindaiBerkas, status PENDING/AMAN/DITOLAK, metadata dan unduhan terkontrol, pelepasan lampiran draft, guard dokumen FINAL untuk bukti/SOP, serta audit atomik tanpa isi dokumen/file.

Hasil: seluruh suite SQLite dan MySQL masing-masing **526 tes lulus, 2.330 assertion**; 14 tes Tahap 6 termasuk matriks role, lintas unit, file palsu/ekstensi ganda/ukuran, freeze dan rollback penyimpanan. Laravel Pint lulus.

Asumsi: upload selalu ditautkan ke versi draft karena ERD file tidak menyimpan unit; pemindai bawaan adalah MIME/ekstensi/checksum, bukan antivirus. Tidak ada perubahan skema. Config awal membolehkan PDF, PNG, JPEG, TXT dan nama dengan satu ekstensi.

Keputusan pengguna: tidak ada keputusan yang menghalangi Tahap 7; ClamAV dapat disediakan kemudian melalui kontrak PemindaiBerkas tanpa klaim antivirus saat ini. Lanjut Tahap 7 berdasarkan persetujuan sampai Tahap 12.


## Tahap 7 - Asesmen inti (13 Oktober 2026)

Selesai: asesmen draft dan seri server, periode berjalan/metode beku, scope pembaca/penyusun, risiko, snapshot aset, tautan RISIKO_ASET, penilaian awal/target dan usulan keputusan. Observer menjaga identitas, snapshot, serta pembekuan bagian awal.

Hasil: seluruh suite SQLite dan MySQL masing-masing **540 tes lulus, 2.449 assertion**; 14 tes Tahap 7. Laravel Pint lulus. R12-R15 diuji melalui API, termasuk tautan lintas asesmen/metode dan rollback transaksi.

Asumsi: STAFF mengubah draft sendiri, ADMIN_SATKER mengelola draft dalam scope; satu penilaian awal per risiko/tahap diperbarui saat draft; snapshot memakai kolom ERD yang ada. Tidak ada perubahan skema. Detail di ASUMSI.md.

Keputusan pengguna: tidak ada yang menghalangi Tahap 8. Workflow dan penilaian residual tidak diklaim sudah selesai pada Tahap 7.


## Tahap 8 - Persetujuan awal dan revisi (13 Oktober 2026)

Selesai: tabel transisi awal, ajukan, review Kasub/Kabid, pemeriksaan jenis/leluhur unit, pemisahan tugas, catatan penolakan, pembekuan awal/penolakan, revisi dengan pemetaan ulang snapshot/risiko/penilaian/usulan/pivot, serta riwayat versi/review.

Hasil: seluruh suite SQLite dan MySQL masing-masing **566 tes lulus, 2.524 assertion**; 26 tes Tahap 8. Laravel Pint lulus. Transisi, reviewer salah, syarat tidak lengkap, audit rollback dan revisi diuji.

Asumsi: skip review mengikuti konfigurasi jenis unit; revisi tetap dalam periode berjalan dan memakai seri/metode semula. Tidak ada perubahan skema. Review dan status tidak dapat diedit langsung.

Keputusan pengguna: tidak ada penghalang Tahap 9; persetujuan akhir dan residual belum diklaim selesai di Tahap 8.


## Tahap 9 - Penanganan, residual dan keputusan akhir (13 Oktober 2026)

Selesai: rencana dengan risiko MITIGASI, progres PIC/Admin, bukti FINAL/AMAN, selesai penanganan, riwayat penilaian aktual, verifikasi Kasub/Kabid, routing penolakan, konfirmasi password Kasatker, keputusan pada penilaian yang benar, residual_tercapai dan pembekuan akhir.

Hasil: seluruh suite SQLite dan MySQL masing-masing **599 tes lulus, 2.637 assertion**; 33 tes Tahap 9. Pint bersih. Tes mencakup matriks peran, isolasi unit, pembekuan, bukti tidak aman, penolakan berulang dan rollback audit.

Asumsi: penolakan residual menyimpan daftar id penilaian yang ditolak pada data_baru audit; pengajuan berikutnya membutuhkan penilaian aktual baru. Urutan tingkat residual memakai skor minimum sel matriks tiap tingkat. STAFF penyusun boleh menambah rencana sesuai narasi residual; progres hanya STAFF PIC atau ADMIN_SATKER. Tidak ada perubahan skema.

Keputusan pengguna: tidak ada penghalang Tahap 10. API aset asli tetap menunggu endpoint lokal; templat Lampiran 14 telah ditanyakan untuk Tahap 11.


## Tahap 10 - Kontrol internal, SOP dan SOA (13 Oktober 2026)

Selesai: CRUD draft, versi SOP/SOA, detail/benchmark SOA, pengajuan dan review berjenjang, scope, pembekuan versi, bukti FINAL/AMAN, serta penautan seluruh pivot kontrol/SOP/risiko/penanganan dalam ERD.

Hasil akhir: seluruh suite SQLite dan MySQL masing-masing **678 tes lulus, 2.833 assertion**; 79 tes Tahap 10. Pint bersih. Kegagalan awal generator ditangani dengan traversal iteratif dan batas memori tes 512 MB. Run MySQL akhir dilakukan setelah formatter selesai dan lulus penuh.

Asumsi: status dan alur SOP/SOA mengikuti config/alur_kepatuhan.php; STAFF menyusun dan mengajukan draft, review Kasub/Kabid/Kasatker, versi selesai tetap. ADMIN_SATKER tidak mengelola SOP/SOA. Tidak ada perubahan skema. Lihat ASUMSI.md.

Keputusan pengguna: templat Lampiran 14 belum jadi; hanya kerangka JSON dibuat pada Tahap 11. Ekspor berkas ditunda tanpa menebak format.


## Tahap 11 - Monitoring dan laporan (13 Oktober 2026)

Selesai: monitoring baca terisolasi per unit, log audit khusus SUPER_ADMIN dengan filter/ekspor CSV aman, kerangka Lampiran 14 JSON per asesmen beserta risiko/penilaian/usulan/keputusan/penanganan.

Hasil: seluruh suite SQLite dan MySQL masing-masing **692 tes lulus, 3.104 assertion**; 14 tes Tahap 11. Pint bersih. Matriks enam role, dua unit, larangan mutasi, pagination, redaksi dan ekspor diuji.

Asumsi: JSON adalah kerangka data, bukan format dokumen resmi. status_template BELUM_TERSEDIA. Tidak ada perubahan skema.

Keputusan pengguna: templat belum jadi. Ekspor Lampiran 14 menunggu templat; penutup Tahap 12 dilanjutkan sesuai otorisasi.


## Tahap 12 - Penutup (14 Oktober 2026)

Selesai: anotasi galat controller, ekstraksi Form Request sesuai default route, respons spesifik resource, skema berdasarkan kolom/cast/hidden model, tipe enum/regex, pagination, cookie Sanctum, unggahan multipart, stream CSV/unduhan, serta identitas operasi unik. README mencakup setup, server, antrean/scheduler, batas akses LOG_AUDIT dan kebutuhan yang masih menunggu pengguna. Pemeriksaan akhir juga melengkapi filter/urutan riwayat dan tautan, query pagination, fixture keputusan, serta larangan menghapus tautan SOP yang masih dirujuk risiko. Tidak ada perubahan skema domain.

Hasil akhir:
- SQLite: **696 tes lulus, 4.119 assertion**.
- MySQL (database tes terpisah): **696 tes lulus, 4.119 assertion**.
- Laravel Pint: **passed**. Opsi --dirty tidak tersedia karena workspace belum memakai Git; seluruh direktori kode yang relevan diperiksa.
- Scramble export --fail-on-unknown: **lulus tanpa diagnostik/skema UnknownType**. OpenAPI 1.0.0 berisi **164 path, 302 operasi, 53 skema**.
- openapi-typescript 7.13.0: **berhasil** menghasilkan tipe dari kontrak final. Artefak: backend/storage/app/openapi.json dan backend/storage/app/openapi.d.ts.

Asumsi: metadata kontrak memerlukan database termigrasi; payload audit dinamis tetap JSON bebas. Route tambahan /api/items ditemukan setelah jeda dan merujuk model Item yang belum tersedia. Route runtime dipertahankan, tetapi tidak dimasukkan dalam kontrak SIMRIS karena berada di luar brief dan skemanya belum disepakati.

Keputusan/kebutuhan pengguna yang masih terbuka:
1. URL endpoint API aset lokal, autentikasi sumber, dan respons asli. Fixture 197 baris saat ini sintetis; koneksi nyata belum diuji.
2. Templat resmi Lampiran 14. Sesuai jawaban pengguna, templat belum jadi; hanya JSON terstruktur tersedia dan format berkas tidak ditebak.
3. Jika /api/items ingin dipakai, model/sumber datanya perlu ditetapkan sebagai pekerjaan terpisah; tidak ditambahkan tabel di luar ERD.

Pekerjaan berhenti setelah Tahap 12 sesuai ruang lingkup yang disetujui. Tidak ada deployment, migrasi database aplikasi, atau integrasi frontend tambahan yang dijalankan.
