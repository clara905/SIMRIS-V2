# Asumsi backend SIMRIS

## Acuan dan lingkungan

- ERD v3 berasal dari lampiran pengguna dan disimpan tanpa perubahan di `docs/erd-v3.mmd`.
- Implementasi mengikuti brief Bagian B, termasuk tambahan yang disetujui pada bagian 3 dan 7a. Kontrak mock frontend bukan sumber skema backend.
- PHP Herd 8.4.26 berhasil dipakai dari `C:\Users\User\.config\herd\bin\php84\php.exe`. Tidak ada instalasi PHP baru. Dependency terpasang mensyaratkan PHP >= 8.4.1.
- Laravel 13.35.0, Sanctum 4.3.3, Pest 5.3.1, Pint 1.32.1; Laravel Boost 2.10.3 dipasang dan bootstrap guidelines dijalankan sesuai AGENTS.md. Scramble 0.13.47 dipasang pada Tahap 2–3 sesuai stack brief.
- MySQL lokal 8.4.3 tersedia. Semua pengujian DDL dilakukan pada database baru `simris_v3_codex_20261012_143505_test`; database aplikasi yang sudah ada tidak dimigrasikan/dihapus.
- Pengguna menyetujui secara eksplisit pengerjaan Tahap 2 lalu Tahap 3 dalam giliran yang sama. Setelah laporan gabungan, pengerjaan berhenti sebelum Tahap 4.

## Rekonsiliasi ERD

- Terdapat 67 tabel pada ERD; SINKRON_ASET yang disetujui menambahnya menjadi 68 tabel domain. Tabel infrastruktur Laravel dipertahankan terpisah.
- SOP_VERSI dan ketiga tabel BUKTI pada ERD sudah memakai dokumen_versi_id, sehingga tidak ada kolom dokumen_id lama yang perlu diganti pada skema baru.
- Tambahan brief: utama dan generated unique key pada penempatan, wajib_ganti_password, dibekukan_awal_pada, kembali_ke, timestamp untuk master/tabel UI termasuk penghubung, indeks gabungan, VIEW, kolom ASET, serta SINKRON_ASET.
- ASESMEN_RISIKO.status tetap varchar. Contoh status lama pada komentar ERD akan digantikan validasi workflow bagian 5; tidak diperlukan kolom/enum database baru.
- PERIODE_PENILAIAN tidak memiliki status. Periode berjalan akan dihitung secara inklusif dari tanggal_mulai sampai tanggal_selesai di Asia/Jakarta.
- AREA_DAMPAK bersifat global. Kriteria terhubung ke versi melalui SKALA_DAMPAK. Perlindungan perubahan area yang memengaruhi metode beku menjadi bagian Tahap 4; tidak ditambahkan versi_metode_id pada area.
- SERI_ASESMEN tidak ditambah unit/periode; aturan satu seri aktif per unit/periode ditegakkan melalui ASESMEN_RISIKO pada tahap layanan.
- ASET.tanggal_pengadaan dan merk menerima NULL sesuai aturan eksplisit bagian 7a; nomor_seri memang nullable dalam ERD. Lokasi tetap wajib di skema dan memakai teks kosong bila belum ditetapkan lokal; lokasi dari API adalah tahun, bukan lokasi fisik.
- VARCHAR dipetakan ke panjang 255 ketika ERD tidak menentukan panjang. nilai_kekritisan menggunakan DECIMAL(15,4); hash_data CHAR(64). PK/FK bigint memakai unsigned untuk konsistensi Laravel/MySQL.
- Seluruh FK domain memakai RESTRICT untuk menjaga sejarah. Relasi RISIKO_KONTROL_SOP ke RISIKO_KONTROL_INTERNAL memakai FK gabungan sesuai relasi ERD.
- Generated key memakai IF pada MySQL; SQLite menggunakan CASE yang ekuivalen untuk tes. Sesuai rumus brief, semua penempatan utama yang selesai IS NULL memesan kunci unik, termasuk yang mulai di masa depan.
- VIEW menghasilkan satu baris per pegawai, nama dan ID setiap level. Pegawai tanpa penempatan utama yang sudah mulai dan belum selesai memiliki jalur NULL. Traversal dibatasi 100 tingkat sebagai pengaman data salah; aturan hierarki dan pencegahan siklus berada di Tahap 3.
- Timestamp pada LOG_AUDIT dan SINKRON_ASET mengikuti kolom waktu khusus ERD/brief, karena bukan tabel yang diedit pengguna. Keduanya tidak diberi created_at/updated_at tambahan.

## Skema awal dan batas tahap

- Migrasi awal Laravel hanya menyiapkan tabel infrastruktur; USERS domain mengikuti username/email_login/password_hash. Migrasi domain menolak database yang masih memiliki users lama, sehingga tidak mengonversi atau membuang akun lama secara diam-diam.
- Nama tabel pada kode mempertahankan huruf besar ERD. MySQL Windows dapat menampilkan nama lowercase karena lower_case_table_names; ini perilaku mesin, bukan perubahan nama aplikasi.
- Model dan factory awal mencakup User, Role, UserRoleScope, UnitOrganisasi, Pegawai, PenempatanPegawai. Model modul lainnya menyusul tahap implementasinya; semua tabelnya sudah dimigrasikan.
- Seeder memakai kredensial dari konfigurasi environment, tidak menanam password. Data demo hanya dibuat saat APP_ENV=local. Seeder mengisi baris yang belum ada tanpa menimpa data atau password yang sudah diubah.
- Scope/Policy administrasi dan audit otomatis telah diimplementasikan pada Tahap 2–3. Status FINAL, pembekuan, dan alur persetujuan tetap mengikuti tahap berikutnya.
- Runtime/ERD tidak lagi menjadi hambatan. Aturan tambahan Bagian A belum dilampirkan; aturan proyek yang dipakai adalah AGENTS.md yang tersedia dan hasil bootstrap Boost.

## Keputusan implementasi Tahap 2–3

- Tidak ada migrasi, tabel, atau kolom domain tambahan pada Tahap 2–3.
- Login menerima `login` (username atau email_login) dan `password`; TOTP hanya diperlukan bila konfigurasi mengaktifkannya untuk SUPER_ADMIN. Nama field ini mengikuti API backend, bukan kontrak mock frontend.
- Konfirmasi tindakan sensitif memakai `POST /api/konfirmasi-password`, kemudian timestamp konfirmasi disimpan di sesi selama 300 detik. Endpoint ini mendukung kebutuhan konfirmasi ulang dalam bagian keamanan brief, tanpa perubahan skema.
- Secret TOTP disediakan per ID pengguna melalui konfigurasi privat, bukan kolom baru. Enrollment/recovery UI tidak ditambahkan; fitur default nonaktif dan gagal tertutup jika diaktifkan tanpa secret valid.
- Staff membaca data pegawai dirinya sendiri; unit/penempatan dibatasi scope. Admin satker dan pimpinan membaca pegawai dari penempatan utama terbuka dalam scope. Pembacaan akun dan role scope khusus SUPER_ADMIN.
- DELETE pada users/unit-organisasi/pegawai berarti nonaktifkan. Roles hanya dibaca. Aturan siklus KONTROL/KATEGORI_ASET akan ditambahkan pada Tahap 5; Tahap 3 menerapkan aturan siklus UNIT_ORGANISASI.
- Perubahan administrasi diserialkan dengan lock baris role SUPER_ADMIN, lalu otorisasi diperiksa kembali, data diubah dan audit dicatat dalam transaksi. Tidak dibuat tabel lock baru.
- Rencana kerja dan periode dapat dibaca semua role aktif dan ditulis SUPER_ADMIN. Periode menggunakan tanggal, bukan kolom status baru.
- Audit pada mutasi model lewat layanan bersifat atomik dan meredaksi data sensitif. Pembatasan SQL langsung membutuhkan akun aplikasi dengan grant per tabel sesuai README; hak akun database lokal yang sudah ada tidak diubah otomatis.
- Scramble menerbitkan endpoint dokumentasi. Resource administrasi bersama menghasilkan peringatan JR001; tipe respons generik belum menjadi kontrak frontend final. Penyempurnaan anotasi per sumber daya tetap bagian Tahap 12.


## Tahap 4 ? Metode risiko (13 Oktober 2026)

- Tidak ada perubahan skema atau dependensi. Model memakai tabel/kolom ERD yang sudah dimigrasikan pada Tahap 1.
- R9 ditegakkan pada setiap mutasi skala: nilai unik dan berurutan dari 1. Tambah nilai berikutnya; hapus dari nilai tertinggi setelah referensinya dilepas. Draft boleh belum memiliki skala/matriks lengkap, tetapi tidak dapat dibekukan sampai validasi lengkap lulus. Batas nilai 46340 menjaga perkalian dua skala tetap dalam INTEGER bertanda.
- Induk versi pada anak, serta induk skala pada kriteria, tidak dapat dipindahkan setelah dibuat. Gunakan pembuatan baru/duplikasi. Ini menjaga konsistensi referensi matriks dan kriteria.
- R10 dihitung di server: skor tidak boleh dikirim melalui API. Kedua skala dan tingkat wajib dari versi sel; indeks unik ERD menjaga satu pasangan. Kelengkapan semua pasangan diperiksa saat validasi/bekukan, sehingga penyusunan draft bertahap tetap mungkin.
- AREA_DAMPAK global sesuai ERD. Area yang dirujuk kriteria versi beku tidak dapat diedit/dihapus. Area baru boleh ditambahkan dan wajib dilengkapi pada draft sebelum dibekukan; versi beku memakai himpunan area dari kriteria yang sudah dibekukan. Tidak ada penambahan kolom versi/snapshot area.
- R11 ditafsirkan sesuai teks: minimal satu kriteria P atau N untuk setiap pasangan dampak ? area. Tidak diwajibkan kedua arah sekaligus atau area minimum tambahan yang tidak dinyatakan brief. Keunikan tetap per (dampak, area, arah); seeder memiliki kedua arah.
- Generate menerima versi_metode_id, tingkat_risiko_id, rekomendasi_keputusan; hanya membuat sel yang belum ada. ERD tidak menyimpan ambang skor per tingkat, sehingga sistem tidak menebak pemetaan tingkat. Admin mengatur tingkat sel sebelum validasi/pembekuan. Pengulangan generate tidak menimpa sel lama.
- Bekukan memerlukan konfirmasi password dalam 300 detik dan seluruh syarat validasi. Observer melindungi versi/anak, termasuk instance yang dibaca sebelum pembekuan. Tidak tersedia aksi buka beku.
- Duplikasi menyalin seluruh skala, tingkat, kriteria, sel ke draft baru dengan pemetaan ulang ID; area global dipakai bersama. Field versi baru wajib diisi; nama/referensi opsional mengikuti sumber. Versi sumber tidak berubah.
- Mutasi model menggunakan transaksi Auditable. Service dan observer memakai lock baris role SUPER_ADMIN yang telah di-seed, sama dengan administrasi, untuk menserialkan perubahan metode dan area terhadap pembekuan. Jalur bulk query builder/raw SQL melewati observer Laravel; penulisan aplikasi modul ini selalu melalui service/model, bukan bulk update/delete.
- Validasi adalah operasi baca (POST aksi) dan tersedia bagi enam role aktif; mutasi hanya SUPER_ADMIN. Master metode bersifat global, tidak difilter berdasarkan unit.
- R12 disediakan sebagai MetodeRisikoService::forAssessment(), wajib dipanggil dalam transaksi asesmen. Guard menolak draft dan mengunci versi beku; integrasi endpoint/model asesmen tetap Tahap 7 sesuai urutan brief, belum diklaim selesai pada Tahap 4.
- Hapus versi draft menghapus anaknya secara atomik setelah memeriksa referensi ASESMEN_RISIKO; foreign key tetap membatasi referensi lain. Versi beku selalu tidak dapat dihapus.


## Tahap 5 - Katalog dan aset

- Pengguna mengonfirmasi API aset lokal dan contoh respons belum tersedia. tests/Fixtures/aset_api_contoh.json adalah fixture SINTETIS 197 baris berdasarkan profil brief, bukan data pengguna. Seluruh tes HTTP memakai Http::fake. Integrasi API lokal asli menunggu URL, autentikasi, dan sampel nyata.
- Driver mengikuti kontrak satu respons tanpa paginasi dengan Bearer opsional. Indikasi paginasi menolak sinkron sebelum perubahan aset. Scheduler dinonaktifkan default sampai konfigurasi tersedia.
- SINKRON_ASET memakai dimulai_pada/selesai_pada tanpa created_at/updated_at sesuai tabel yang sudah disetujui; model mematikan timestamp otomatis. Tidak ada migrasi baru.
- Riwayat sinkron berisi data lintas unit/peringatan sumber, sehingga akses dibatasi SUPER_ADMIN bersama pemicunya. Aset dan penugasan tetap dibatasi scope unit; SUPER_ADMIN hanya baca aset sesuai brief.
- Scope mutasi aset dihitung khusus dari grant STAFF, bukan gabungan grant Admin Satker/pimpinan di unit lain. Penugasan memerlukan pegawai aktif dengan penempatan dalam scope staff.
- Impor CSV ancaman/kerentanan/kontrol adalah pembuatan per baris, bukan upsert. Header memakai nama kolom ERD, UTF-8, delimiter koma, batas 2 MiB dan 10000 baris. Baris valid disimpan dengan audit atomik; baris gagal dilaporkan. Ekspor menetralkan awalan formula spreadsheet.
- Hierarki kontrol tidak boleh bersiklus atau lintas standar; kategori tidak boleh bersiklus. ANCAMAN_SUMBER dikelola melalui sumber_ids pada mutasi ancaman dan dicatat audit terpisah.
- Sinkron menjaga kolom lokal dan kode_aset; pembaruan sumber berdasarkan hash bentuk normal. Satu transaksi per potongan 200 baris. idx valid pada baris ditolak tetap dihitung hadir agar tidak salah ditandai hilang. Jika semua idx tidak valid, penandaan hilang tidak dilakukan.
- API kode_aset bentukan yang ternyata juga bentrok ditolak per baris dan dilaporkan untuk keputusan penamaan, tanpa mengubah aturan kode yang diberikan brief.
- Queue database retry_after default 1200 detik, lebih besar dari timeout job sinkron 900 detik; lock 3600 detik mencakup antrean dan eksekusi. Riwayat job gagal menyimpan pesan aman tanpa token/response mentah. Ketika job mulai setelah lock kedaluwarsa, job gagal tanpa menyentuh aset.


## Tahap 6 - Dokumen dan lampiran

- Tidak ada perubahan skema. FILE_LAMPIRAN tidak memiliki unit; unggahan wajib menyebut dokumen_versi_id dan langsung ditautkan ke draft. Scope file diturunkan dari dokumen terkait, tidak ditebak dari identitas pengunggah.
- STAFF dalam scope dapat membuat dokumen, menambah/edit draft, mengunggah/melepas lampiran, dan memfinalkan. ADMIN_SATKER hanya mengelola akun dan data pegawai. Semua role membaca dalam scope; SUPER_ADMIN baca global.
- Nomor versi dokumen dihasilkan berurutan di bawah lock dokumen. Versi FINAL tidak dapat dibuka kembali. Semua lampiran harus AMAN sebelum FINAL, termasuk bila dokumen memiliki teks.
- Disk lampiran privat terpisah, tanpa serve/URL publik. Nama penyimpanan acak; storage_key tidak ditampilkan. Download memeriksa scope, status AMAN, keberadaan, dan checksum.
- Pemindai bawaan memeriksa MIME dari isi, checksum, ukuran dan nama dengan satu ekstensi; nama seperti laporan.v1.pdf ditolak oleh aturan ekstensi ganda. Konfigurasi awal PDF, PNG, JPG/JPEG, TXT, maksimum 10 MiB. Ini pemeriksaan dasar, bukan pemindai malware/ClamAV.
- Job mengubah PENDING menjadi AMAN/DITOLAK; file tidak ditimpa. Teks dokumen juga disaring dari snapshot audit (isi_teks) agar konten tidak tersalin ke LOG_AUDIT. Metadata dan kejadian tetap diaudit.
- R17 tersedia melalui DokumenService::finalVersion() untuk modul bukti/SOP berikutnya. Semua tautan dokumen nantinya harus memakai guard ini.


## Tahap 7 - Asesmen inti

- Tidak ada perubahan skema. Seri baru dibuat server dengan kode UUID; satu versi aktif per unit/periode dicegah di bawah lock unit dan role. Metode, unit, periode, seri dan pembuat tidak dapat dipindahkan setelah pembuatan.
- STAFF mengubah draft yang disusunnya sendiri; ADMIN_SATKER dapat mengelola draft dalam scope role adminnya. Semua role membaca transaksi dalam scope; SUPER_ADMIN baca global.
- Pemilik risiko harus pegawai aktif dengan penempatan dalam scope pengguna. Kolom skenario, uraian kerentanan/dampak, area, ancaman dan kerentanan memakai nama ERD.
- Snapshot aset merekam nama kategori/klasifikasi (string kosong untuk klasifikasi NULL), lokasi, serta jalur unit berupa JSON di kolom TEXT yang sudah ada. Nilai/dasar kekritisan dapat diedit saat draft, sedangkan atribut snapshot tidak dapat ditimpa. Sinkron/perubahan master tidak memperbarui snapshot.
- RISIKO_ASET diatur melalui asesmen_aset_ids pada mutasi risiko; semua ID harus milik asesmen yang sama. Penggantian tautan dan audit berada dalam satu transaksi.
- Penilaian SAAT_INI/TARGET_SETELAH disimpan satu baris per risiko/tahap melalui service saat draft; POST ulang mengubah baris tersebut. Usulan keputusan mengacu pada penilaian SAAT_INI. AKTUAL_SETELAH dan bukti residual menyusul Tahap 9 sebagai riwayat append-only.
- DELETE risiko dibatasi foreign key jika sudah dirujuk; snapshot yang dipakai risiko harus dilepas lebih dahulu. Tidak ada penghapusan asesmen melalui API.


## Tahap 8 - Persetujuan awal

- Semua perpindahan status asesmen awal melalui AsesmenWorkflow dan config/alur_asesmen.php. AsesmenObserver menolak perubahan status langsung tanpa jalur workflow internal; API tidak menerima status dari klien.
- config/alur_review.php menentukan jenis unit role dan tahap yang dilewati: BIDANG/BAGIAN melewati Kasub; SATKER melewati Kasub dan Kabid. Penyelesaian tahap awal yang dilewati tetap mengisi dibekukan_awal_pada dan memilih PENANGANAN/MENUNGGU_KASATKER dari usulan MITIGASI.
- Reviewer harus punya grant aktif pada unit asesmen atau leluhur dengan jenis yang tepat. Grant pada cabang lain atau jenis unit yang salah tidak cukup. Pembuat penilaian tidak boleh mereview, dan reviewer Kasub tidak boleh menjadi Kabid/Kasatker pada asesmen sama.
- Revisi DITOLAK mempertahankan metode, seri, periode, snapshot dan penilaian awal/usulan; seluruh ID anak/pivot dipetakan ulang. Nomor versi berikutnya dihitung dari maksimum seri di bawah lock, dan satu versi aktif unit/periode tetap dijaga. Periode revisi juga harus masih berjalan.
- Review append-only; status, review, salinan revisi dan audit berada dalam transaksi. Tidak ada perubahan skema.


## Tahap 9
- Riwayat AKTUAL_SETELAH selalu append, bukan overwrite. Audit penolakan menyimpan residual_ditolak_ids agar pengajuan ulang memerlukan penilaian baru tanpa menambah kolom skema atau bergantung presisi timestamp.
- residual_tercapai membandingkan tingkat berdasarkan skor minimum matriks dalam versi metode yang sama.
- STAFF penyusun boleh membuat rencana; pelapor progres wajib PIC atau ADMIN_SATKER dalam scope.
- Status rencana diperbarui melalui laporan progres. Bukti hanya dokumen FINAL dengan seluruh file AMAN.


## Tahap 10
- Brief tidak merinci status SOP/SOA; config/alur_kepatuhan.php memakai DRAFT, MENUNGGU_KASUB, MENUNGGU_KABID, MENUNGGU_KASATKER, DISAHKAN, DITOLAK. Penolakan membuat versi lama tetap; penyusun membuat versi baru melalui endpoint versi. Tidak menambah kolom skema.
- STAFF dapat menyusun, mengajukan, dan menghapus draft dalam scope. ADMIN_SATKER hanya mengelola akun dan data pegawai. Reviewer tidak mereview versi sendiri; Kasub tidak merangkap reviewer berikutnya. Skip level mengikuti config/alur_review.php.
- Tautan operasional memakai SOP DISAHKAN dan dokumen FINAL/AMAN. Detail SOA hanya memakai kontrol dari standar SOA. Standar/periode induk tidak berubah setelah versi dibuat.
- Scope bertingkat ditelusuri secara iteratif agar analisis tipe Scramble tidak rekursif. PHPUnit diberi batas memori 512 MB untuk keseluruhan kontrak API.

## Kebutuhan Lampiran 14
Pengguna menyatakan templat belum jadi. Tahap 11 hanya menyediakan kerangka JSON sesuai brief. Ekspor berkas menunggu templat; tidak menghalangi penutup Tahap 12 yang telah diotorisasi.


## Tahap 11
- Monitoring adalah alias baca untuk asesmen/SOA/SOP/dokumen/lampiran, memakai scope yang sama dengan endpoint operasional.
- Lampiran 14 menerima asesmen_id, mengembalikan daftar risiko terstruktur dengan pagination dan metadata asesmen. Tidak menerima format ekspor; status_template BELUM_TERSEDIA sesuai jawaban pengguna.
- Log audit hanya SUPER_ADMIN, tidak ada mutasi. Ekspor CSV mempertahankan filter, menetralkan formula sel, dan menerapkan redaksi sensitif kembali pada output.


## Tahap 12
- Ekstensi Scramble membaca Form Request dengan default route agar controller bersama menghasilkan body spesifik per resource. Respons dideklarasikan per endpoint dan skema kolom/cast/hidden model; metadata memerlukan database termigrasi. Payload audit dinamis didokumentasikan sebagai JSON bebas.
- Kode status, pagination, cookie Sanctum, multipart unggahan, stream unduhan/CSV, dan identitas operasi unik dicakup tes kontrak. Tidak mengubah autentikasi atau skema domain.
- Pemeriksaan penutup menambahkan filter/urutan daftar tautan dan riwayat, mempertahankan query pagination, serta mencegah penghapusan tautan SOP yang masih dirujuk risiko.
- Route /api/items dan ItemController ditemukan setelah jeda pada 14 Oktober 2026. Itu bukan modul brief; model Item belum ada. Perubahan tersebut dipertahankan dan tidak ditambahkan skema baru.

Kontrak OpenAPI SIMRIS mengecualikan /api/items sampai modul tambahan tersebut memiliki model dan kontrak yang disepakati. Endpoint runtime tetap dipertahankan. Enum dan regex tekstual diberi tipe string eksplisit pada ekstraksi dokumentasi.
