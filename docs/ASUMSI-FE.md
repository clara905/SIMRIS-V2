# Asumsi dan selisih kontrak frontend

- Pemeriksaan awal 12 Oktober 2026: backend/routes/api.php hanya menyediakan
  GET /api/user dengan auth:sanctum. routes/web.php hanya halaman welcome.
  Backend tidak diubah.
- docs/erd-v3.mmd dan dokumen OpenAPI belum tersedia di checkout. Istilah
  mengikuti brief; relasi dan validasi belum dapat dinyatakan sesuai ERD.
- Pengembangan memakai MSW dengan kontrak OpenAPI sementara yang diberi
  label eksplisit. schema.d.ts dihasilkan dari kontrak tersebut, bukan
  diklaim sebagai skema backend. Ganti sumber gen:api saat API tersedia.
- Mode mock diaktifkan eksplisit lewat VITE_API_MOCK=true. Cookie Sanctum
  produksi tetap menggunakan withCredentials dan withXSRFToken. Mock
  menggunakan sesi lokal untuk demonstrasi, bukan mekanisme keamanan produksi.
- Aturan role scope, reset password, pembekuan, impor, dan penonaktifan
  wajib ditegakkan ulang backend. Validasi frontend bukan batas keamanan.
- Mode gelap belum diaktifkan; perlu audit pasangan kontras tersendiri.

## Perilaku mock yang perlu diganti pada integrasi
- Data master simulasi tersimpan di localStorage pada browser yang sama;
  sesi demonstrasi di sessionStorage. Tidak ada klaim penyimpanan server.
- Endpoint sementara: /api/pengguna, /api/unit, /api/pegawai, /api/metode,
  /api/master, /api/monitoring, /api/audit. Respons daftar { data: T[] };
  mutasi { data: T }; validasi { message, errors } dengan status 422.
- Role scope memakai unit_id, tanggal mulai dan berakhir. Penempatan baru
  mengakhiri penempatan aktif sebelumnya sehari sebelum tanggal mulai.
- Reset kata sandi dan sinkronisasi sumber adalah simulasi. Tidak mengirim
  email, tidak menghubungi sistem inventaris eksternal.
- Katalog/standar/kontrol/regulasi/aset/periode memakai model Master sementara
  dengan jenis pembeda. Pisahkan adapter bila kontrak backend berbeda.
- CSV menerima header kode,nama,deskripsi; maksimal 1 MB/1.000 baris;
  seluruh batch harus valid. Tidak mengganti data yang sudah ada.
- Tanggal data contoh berada pada periode kerja Oktober 2026. Informasi
  aktual ditampilkan dengan Intl id-ID dan zona Asia/Jakarta.
- Kata sandi demo yang diganti disimpan sebagai digest lokal untuk menguji
  masuk ulang; ini bukan implementasi penyimpanan kata sandi produksi.
- Impor kontrol menempatkan baris baru di akar standar yang sedang dipilih;
  hierarki lanjutan diatur lewat formulir induk kontrol.
- Pembekuan mock memerlukan satu kriteria per kombinasi area dan skala
  dampak, skala unik, tingkat valid, serta matriks lengkap dengan skor hasil
  perkalian. Selaraskan aturan ini dengan kontrak backend saat tersedia.
- Monitoring dokumen/lampiran menampilkan metadata dan riwayat simulasi.
  Unduhan berkas asli belum dapat diintegrasikan tanpa endpoint berkas.

## Keputusan integrasi yang masih diperlukan

Sediakan ERD v3 dan OpenAPI backend yang sebenarnya untuk memfinalkan nama
field, relasi, pagination server, enum role/status dan aturan domain.
Pengembangan frontend dengan mock sudah dapat ditinjau tanpa keputusan itu;
penggunaan data nyata tetap memerlukan penyelarasan dan uji integrasi Sanctum.
