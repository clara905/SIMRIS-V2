# SIMRIS — frontend super admin

Jalankan dari folder `frontend` dengan Node.js 22.12+ (diverifikasi pada Node 24).

```powershell
npm ci
npm run dev
```

Buka http://localhost:5173/masuk. Mode pengembangan memakai MSW secara eksplisit
melalui `.env.development`. Akun demo: `admin@simris.go.id` / `Simris123!`.
Akun `baru@simris.go.id` menguji ganti password wajib, dan
`terbatas@simris.go.id` menguji penolakan akses. Data demo tersimpan di browser.

## Backend nyata

Salin `.env.example` ke `.env.local`, atur `VITE_API_MOCK=false` dan
`VITE_API_URL` ke origin Laravel. Pastikan Sanctum, CORS kredensial, domain
stateful dan cookie dikonfigurasi di backend. Frontend tidak mengubah backend.

```powershell
npm run gen:api
npm run typecheck
```

Generator API nyata menggunakan http://localhost:8000/docs/api.json. Ubah URL
jika berbeda. Kontrak mock belum diverifikasi dengan backend atau ERD;
lihat `../docs/ASUMSI-FE.md`. Jangan menganggap mock sebagai kontrol keamanan.
Build produksi tidak mengaktifkan mock kecuali variabelnya diatur eksplisit.

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
