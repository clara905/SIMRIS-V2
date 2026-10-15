# SIMRIS — frontend

Jalankan dari folder `frontend` dengan Node.js 22.12+ (diverifikasi pada Node 24).

```powershell
npm ci
npm run dev
```

Buka http://localhost:5173/masuk. Mode pengembangan sekarang memakai backend Laravel.
Untuk demonstrasi lama, jalankan dengan environment `VITE_API_MOCK=true`. Akun demo: `admin@simris.go.id` / `Simris123!`.
Akun `baru@simris.go.id` menguji ganti password wajib, dan
`terbatas@simris.go.id` menguji penolakan akses. Data demo tersimpan di browser.

## Backend nyata

Salin `.env.example` ke `.env.local`, atur `VITE_API_MOCK=false` dan
`VITE_API_URL` ke origin Laravel. Pastikan Sanctum, CORS kredensial, domain
stateful dan cookie dikonfigurasi di backend. Frontend tidak mengubah backend.

```powershell
npm run typecheck
```

Ruang kerja backend memakai endpoint dan nama kolom Laravel. Skema di
`src/api/schema.d.ts` masih dipakai halaman demonstrasi lama; jangan menimpanya
dengan generator backend tanpa memigrasikan halaman demonstrasi tersebut.
Build produksi tidak mengaktifkan mock kecuali variabelnya diatur eksplisit.

## Ruang kerja baru (14 Oktober 2026)

- Tampilan merah-putih responsif dengan dashboard, navigasi pencarian menu, dan panel rincian.
- Sesi `/api/me` dinormalisasi dari `user`, `pegawai`, dan `roles`. Keenam role backend dapat masuk; menu pengguna dan audit hanya untuk SUPER_ADMIN. Backend tetap menentukan hak akses setiap data.
- Login mengirim `login`, `password`, serta TOTP opsional. Ganti kata sandi memakai `/api/ganti-password` dan menyertakan `current_password`.
- Ringkasan memakai `meta.total` dari API sesuai lingkup pengguna. Register memakai paginasi server dan filter ID/status; angka kosong tidak diganti data contoh.
- Tersedia register asesmen, aset, SOP/SOA, kontrol internal, dokumen/lampiran, metode, katalog, standar/regulasi, periode, organisasi, pegawai, pengguna, dan audit.
- Rincian asesmen memuat risiko, rencana penanganan, serta data Lampiran 14. Dokumen/SOP/SOA dapat dibuka riwayat versinya. Lampiran AMAN dapat diunduh dan audit dapat diekspor CSV.
- Ruang kerja baru adalah antarmuka pemantauan. Form CRUD domain, penilaian, dan aksi persetujuan belum dihubungkan di UI baru. Form administrasi lama tetap berada di mode demonstrasi.
- Ekspor dokumen Lampiran 14 masih menunggu template risiko yang sesuai; template pengaduan aset tidak dipaksakan ke skema risiko.

Tes browser kontrak backend (respons API fixture, tanpa mengubah database):

```powershell
npx playwright test --config playwright.backend.config.ts
```

Konfigurasi tes menjalankan Vite pada port 5175 dengan mock MSW nonaktif.
Tes mencakup sesi, login/TOTP, ganti sandi, pembatasan menu, filter/paginasi,
rincian, pemulihan error, tampilan ponsel, dan aksesibilitas dashboard.

## Pemeriksaan

```powershell
npm run typecheck
npm run lint
npm run test -- --run
npm run build
npm run e2e
```

Playwright menggunakan http://127.0.0.1:5173. Jalankan server pengembangan
terlebih dahulu. Jika browser belum tersedia: `npx playwright install chromium`.
Pengujian mencakup autentikasi, organisasi, metode beku, CSV, monitoring,
403, aksesibilitas axe, dan responsivitas. Screenshot setiap tahap berada
di `artifacts/tahap-*`; direktori ini tidak dilacak Git.

```powershell
npm run gen:mock
node scripts/capture.mjs tinjauan / /pengguna /metode-risiko/2
```

Galeri komponen: `/dev/komponen`. Pintasan: `/` pencarian, `?` bantuan,
`g` lalu huruf modul. Mode gelap belum diaktifkan.

Laporan, asumsi kontrak, dan keputusan desain berada di `../docs/`.
