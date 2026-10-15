# Laporan implementasi

## Pembaruan UI terhubung backend — 14 Oktober 2026

### Hasil

- Ruang kerja baru merah-putih dengan dashboard, sidebar pencarian menu, kartu jumlah data, tahap asesmen, register, dan rincian dalam drawer. Tata letak desktop serta ponsel sudah diperiksa melalui screenshot.
- Mode pengembangan default berpindah ke backend nyata (`VITE_API_MOCK=false`). Halaman demonstrasi lama tetap tersedia melalui mode mock eksplisit.
- Login disesuaikan ke field `login`, TOTP opsional, normalisasi respons `/api/me`, dan ganti kata sandi dengan `current_password` melalui `/api/ganti-password`.
- Akses ruang kerja mendukung role backend aktif; menu pengguna dan audit hanya untuk SUPER_ADMIN. Otorisasi data tetap dilakukan backend.
- Register membaca endpoint backend untuk asesmen, aset, kepatuhan, dokumen, referensi, organisasi, pegawai, pengguna, dan audit. Jumlah data berasal dari metadata paginasi server; filter ID/status dan perpindahan halaman dikirim ke API.
- Rincian asesmen menampilkan risiko, rencana penanganan, dan data Lampiran 14. Riwayat versi SOP/SOA/dokumen, unduhan lampiran AMAN, dan ekspor CSV audit memakai endpoint yang sudah tersedia.

### Verifikasi

- Build TypeScript/Vite: lulus. Peringatan ukuran chunk utama di atas 500 kB masih muncul; bukan kegagalan build.
- ESLint: lulus.
- Seluruh tes unit frontend: 11 tes lulus dalam 5 berkas.
- Suite browser khusus kontrak backend: 5 skenario lulus. Mencakup jumlah data, filter/paginasi server, rincian risiko/laporan, akses STAFF, login/TOTP, ganti sandi, error/retry, empty state, dan navigasi ponsel.
- Axe dashboard: tidak ada pelanggaran WCAG A/AA pada pemeriksaan yang dijalankan. Lebar 390, 768, dan 1024 tidak menyebabkan overflow halaman; screenshot desktop 1440 dan ponsel 390 diperiksa.
- Tes browser memakai fixture respons sesuai bentuk kontrak backend, bukan login ke database pengguna. Koneksi end-to-end ke server Laravel pengguna belum diverifikasi. Suite browser demonstrasi lama tidak dijalankan ulang.
- Tidak ada perubahan PHP, skema, atau database; tes Laravel dan Pint tidak dijalankan ulang untuk perubahan frontend ini.

### Asumsi dan batas

- Permintaan UI baru diterapkan sebagai ruang kerja pemantauan sesuai endpoint backend. Form CRUD domain, pengisian penilaian, dan tombol persetujuan belum dihubungkan di UI baru; tidak ada tombol yang mengklaim aksi tersebut sudah tersedia.
- ID relasi ditampilkan dengan label ID agar tidak dianggap sebagai nama unit atau pegawai yang sudah di-resolve.
- Template pengaduan aset tidak dianggap sebagai template Lampiran 14. Ekspor dokumen tetap menunggu keputusan template yang sesuai.
- Tidak memerlukan keputusan skema atau arsitektur baru. Panduan menjalankan dan batas integrasi ada di `frontend/README-SIMRIS.md`.

## Tahap 0 — selesai
- DESIGN.md memuat token, lima wireframe, prinsip dan kritik desain awal.
- Baseline: typecheck, lint, 1 tes, build lulus.
- Screenshot 1440, 1024, 390 di frontend/artifacts/tahap-0 diperiksa langsung.
- Baseline hanya judul SIMRIS; tidak dianggap sebagai hasil antarmuka.
- ERD dan OpenAPI backend belum ada. Kontrak sementara didokumentasikan.
- Keputusan: register tindak lanjut menggantikan kartu statistik; tidak
  memerlukan keputusan tambahan untuk melanjutkan pengembangan mock.

## Tahap 1 — selesai
- AppShell responsif, sidebar ciut/laci, Sanctum axios, MSW, route terlindungi,
  masuk, ganti password wajib, tanpa akses. Font self-hosted dan strict TS.
- Typecheck, lint, 1 tes komponen, 3 Playwright, build lulus.
- Screenshot tahap-1 pada 1440/1024/390 diperiksa: shell, masuk, tanpa akses.
- Semua autentikasi diverifikasi terhadap mock; backend belum menyediakan
  endpoint yang diminta. Bukan bukti integrasi produksi.
- TypeScript 5.9 dipilih agar sesuai peer dependency openapi-typescript.
- Build memberi peringatan ukuran bundle; pemisahan route dijadwalkan tahap 7.

## Tahap 2 — selesai
- DataTable TanStack, FilterBar, Drawer, ConfirmDialog, TreeView, status,
  risiko, empty/error/skeleton dan galeri /dev/komponen tersedia.
- Typecheck, lint, 5 tes Vitest, 4 Playwright dan build lulus.
- Screenshot galeri pada tiga lebar diperiksa. Tabel bergulir lokal,
  konfirmasi mempunyai focus trap dan Escape diuji pada drawer.
- TanStack Table v9 memakai adapter legacy resmi yang disediakan paket.
- Kontras teks risiko dihitung untuk memilih hitam/putih dengan rasio AA.

## Tahap 3 — selesai
- Pengguna/form/role scope/reset, unit/pohon/pindah induk, pegawai/form/
  penempatan dan riwayat tersedia. Mutasi menggunakan hook Query dan MSW.
- Typecheck, lint, 5 tes Vitest, 7 Playwright, build lulus.
- Screenshot daftar pengguna, unit, pegawai pada tiga lebar diperiksa.
- Larangan nonaktif ditampilkan dengan alasan; riwayat dipertahankan.
- Reset password masih simulasi tanpa email. Tanggal dan aturan penempatan
  merupakan kontrak sementara sampai ERD dan API tersedia.

## Tahap 4 — selesai
- Editor lima tab, matriks dengan seleksi jamak, tooltip kriteria,
  panel masalah, pembekuan dengan ketik nama, segel dan duplikasi tersedia.
- Typecheck, lint, 7 tes Vitest, 8 Playwright, build lulus.
- Screenshot daftar, matriks draf dan beku pada tiga lebar diperiksa.
- Tes browser memastikan semua tab beku tidak memiliki tombol pengubah.
- MSW menolak perubahan versi beku. Skala awal merupakan data simulasi;
  pengguna perlu menyesuaikannya sebelum memakai metode nyata.

## Tahap 5 — selesai
- Katalog ancaman/kerentanan, standar/kontrol/regulasi, aset (sumber,
  kategori pohon, klasifikasi), periode dan rencana kerja tersedia.
- Impor CSV berpratinjau dan validasi batch; ekspor berpratinjau; status
  pemakaian transaksi menghilangkan kebutuhan aksi hapus.
- Typecheck, lint, 9 tes Vitest, 10 Playwright, build lulus.
- Screenshot katalog, pohon kontrol, aset dan periode pada tiga lebar
  diperiksa. Sinkronisasi tetap simulasi sesuai ASUMSI-FE.md.

## Tahap 6 — selesai
- Beranda dengan tindak lanjut, batang status asesmen, dan aktivitas audit.
- Monitoring asesmen/SOA/SOP/dokumen/lampiran hanya-lihat; lembar asesmen
  mempunyai tahap penilaian dan riwayat versi. Audit mempunyai filter dan diff.
- Typecheck, lint, 9 tes Vitest, 13 Playwright, build lulus.
- Screenshot beranda, monitoring, lembar asesmen, dan audit pada tiga lebar
  diperiksa. Tes mencakup respons API 403 dan tidak adanya tombol mutasi
  transaksi di semua tab monitoring.

## Tahap 7 — selesai untuk frontend dengan kontrak mock

- Route fitur dimuat secara lazy; font self-hosted dibatasi ke subset Latin.
  Pintasan /, ?, dan g lalu huruf tersedia, disertai pengaman saat mengetik
  atau dialog terbuka. Baris tersimpan disorot sementara.
- Role scope dapat diubah; super admin terakhir dilindungi. Impor kontrol
  mengikuti standar terpilih. Reset/ganti password demo diuji hingga masuk
  ulang. Pembekuan memvalidasi skala, kriteria per area, tingkat, dan matriks.
- Galeri dilengkapi matriks kosong/terpilih/hanya-lihat, segel dan diff.
  Ikon pohon tablet, tautan audit unit, dan pengumuman galat form diperbaiki.
- Typecheck dan lint lulus. **11 tes Vitest pada 5 berkas lulus**.
  **36 tes Playwright lulus** pada eksekusi akhir (Chromium, 1,7 menit).
- Axe dengan tag WCAG 2 A/AA, 2.1 AA, 2.2 AA: nol pelanggaran otomatis
  pada 19 route utama, halaman ganti password, dan empat keadaan overlay.
  Ganti password dan overlay diuji pada ketiga lebar (34 pemindaian total).
- Navigasi keyboard, focus trap, Escape, pintasan, navigasi ponsel, pembesaran
  CSS 200%, serta overflow halaman pada 360/390/768/1024/1440 diuji.
  Ini bukan sertifikasi WCAG atau pengganti audit pembaca layar menyeluruh.
- **54 screenshot akhir** pada 1440/1024/390 di
  `frontend/artifacts/tahap-7` diperiksa langsung: 13 tampilan × 3 lebar
  serta 5 keadaan form/dialog × 3 lebar. Screenshot tahap sebelumnya tetap
  tersimpan di direktori tahap masing-masing.
- Build produksi lulus. Bundle utama sekitar 547 kB (173 kB gzip) masih
  menghasilkan peringatan ambang 500 kB dari Vite. Halaman fitur telah
  dipisah; peringatan tidak disembunyikan dan bukan kegagalan build.
- Kritik desain akhir: chip “Tercatat” yang repetitif pada aktivitas beranda
  dibuang dan diganti tautan rincian. Garis, hierarki tipografi, matriks dan
  segel tetap menjadi identitas; tidak menambah kartu statistik dekoratif.
- Backend tidak diubah. Endpoint domain, ERD v3 dan OpenAPI asli belum
  tersedia dalam checkout. Integrasi produksi, email reset, sinkronisasi
  inventaris nyata dan berkas lampiran asli belum diklaim selesai.
- Keputusan yang dibutuhkan saat integrasi: kontrak API/ERD sebenarnya,
  enum role/status dan aturan domain. Tidak ada keputusan tambahan yang
  diperlukan untuk meninjau frontend dalam mode demo sekarang.

## Menjalankan dan bukti

Lihat `frontend/README-SIMRIS.md` untuk perintah, akun demo, mode backend
nyata dan regenerasi tipe. Dari `frontend`, jalankan `npm run dev`.
Log akhir: `frontend/artifacts/build-final.log` dan
`frontend/artifacts/e2e-final.log`. Server lokal milik sesi pengujian
dihentikan setelah pemeriksaan; jalankan kembali untuk meninjau aplikasi.

## Revisi merah–putih

- Aksen merah–putih, navigasi aktif merah solid, respons hover/fokus,
  serta filter prioritas beranda diterapkan sesuai permintaan lanjutan.
- Typecheck, lint, build, 11 tes Vitest dan 23 tes Playwright terpilih lulus.
  Axe pada 19 halaman tidak menemukan pelanggaran otomatis.
- Sembilan screenshot beranda, pengguna, dan metode beku pada
  1440/1024/390 diperiksa di `frontend/artifacts/merah-putih`.
- Log pemeriksaan: `frontend/artifacts/e2e-red-white.log` dan
  `frontend/artifacts/build-red-white.log`. Peringatan ukuran bundle
  utama dari build sebelumnya masih ada; tidak ada galat build.

## Revisi futuristik

- Sidebar konsol gelap, ruang kerja grid putih, judul sans tebal, sudut
  panel asimetris, header sticky, dan cahaya aksen merah diterapkan.
- Typecheck, lint, build, 11 tes unit dan 20 tes browser aksesibilitas/
  responsivitas lulus. Axe pada 19 halaman tanpa pelanggaran otomatis.
- Sembilan screenshot beranda, pengguna dan login pada 1440/1024/390
  diperiksa di `frontend/artifacts/futuristik`.
- Log: `frontend/artifacts/e2e-futuristik.log` dan
  `frontend/artifacts/build-futuristik.log`. Peringatan ukuran bundle
  sebelumnya masih ada. Backend dan alur data tidak diubah.
