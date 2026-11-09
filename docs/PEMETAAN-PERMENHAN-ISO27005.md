# Pemetaan Form Asesmen Risiko

Dokumen ini mencatat pemetaan keputusan produk ke data dan alur SIMRIS. Ini bukan tafsir hukum atau pernyataan sertifikasi/kepatuhan. Materi yang tersedia saat audit hanya batang tubuh Permenhan No. 17 Tahun 2021 Pasal 1–22; Lampiran I dan II tidak tersedia. Karena itu, angka, deskriptor, dan tata letak di bawah tidak diklaim berasal dari lampiran tersebut.

## Keputusan produk yang disetujui

| Area | Keputusan | Penyimpanan/perilaku SIMRIS |
|---|---|---|
| Kemungkinan | 1 Sangat jarang; 2 Jarang; 3 Mungkin; 4 Sering; 5 Hampir pasti | Label dan kriteria skala per versi metode. Rentang probabilitas/frekuensi masih TBD. |
| Dampak | 1 Tidak signifikan; 2 Minor; 3 Moderat; 4 Mayor; 5 Katastropik | Kriteria per versi metode dengan kolom opsional kerahasiaan, integritas, ketersediaan, kepatuhan, dan operasional. Isi kriteria masih TBD; tidak diisi dengan asumsi. |
| Skor dan tingkat | Skor kemungkinan × dampak; 1–4 Rendah, 5–9 Sedang, 10–16 Tinggi, 17–25 Sangat Tinggi | Skor tetap dihitung backend. Profil penilaian baru menyimpan batas skor dan aturan penerimaan pada `TINGKAT_RISIKO`; tidak memetakan ulang versi legacy. |
| Penerimaan dan RTP | Rendah dapat diterima; Sedang memerlukan persetujuan pemilik risiko; Tinggi/Sangat Tinggi wajib RTP | Data kriteria, `perlu_persetujuan_pemilik`, dan `perlu_rtp` dibekukan bersama versi metode. Persetujuan Sedang memakai tahap review Kasatker yang sudah ada. |
| Perlakuan | `MITIGASI` → Modify; `TERIMA` → Retain; `HINDARI` → Avoid; `ALIHKAN` → Share/Bagikan | Kode penyimpanan lama dipertahankan; label UI menerjemahkan maknanya. |
| Tahap penilaian | `SAAT_INI` = risiko dengan kontrol yang ada; `TARGET_SETELAH` = target setelah RTP; `AKTUAL_SETELAH` = aktual setelah penanganan | Nilai tahap lama dipertahankan. Target yang lebih tinggi boleh dengan alasan eksplisit tersimpan pada penilaian. |
| Tanggung jawab | STAFF penyusun utama; KASUB/KABID/KASATKER meninjau | Tidak mengubah peran, policy, scope, pemisahan tugas, atau transisi workflow. Identitas jabatan pemilik risiko/pemberi persetujuan masih TBD. |

## Alur layar dan payload

| Tahap/konsep | Data dan layar | Perilaku |
|---|---|---|
| Menetapkan konteks | Tujuan, ruang lingkup, periode, dan `RISIKO.konteks` | Periode adalah rentang pelaksanaan, bukan parameter perkalian skor. Konteks memakai STRATEGIS, OPERASIONAL, PELAPORAN, atau KEPATUHAN. |
| Identifikasi berbasis aset | Snapshot `ASESMEN_ASET`; tautan `RISIKO_ASET`; ancaman, kerentanan, dan konsekuensi K/I/A pada `RISIKO` | Snapshot dapat ditautkan pada risiko dalam asesmen yang sama. Nilai K/I/A merekam uraian konsekuensi, tidak mengganti skala dampak metode. |
| Analisis dan peta | `PENILAIAN_RISIKO` dengan `SAAT_INI`, `TARGET_SETELAH`, `AKTUAL_SETELAH`; endpoint `GET /api/asesmen/{id}/peta-risiko` | Profil baru mengirim L/I integer 1–5. Backend menghitung skor/tingkat dari metode beku. Peta memuat semua sel matriks per tahap dengan jumlah dan ID risiko; UI dapat memfilter tabel dengan klik atau keyboard. |
| Evaluasi dan perlakuan | Perbandingan tingkat per tahap; `USULAN_KEPUTUSAN`; `RENCANA_PENANGANAN` pada tahap penanganan | Usulan adalah pilihan perlakuan dan ringkasan awal. RTP formal tetap mengikuti tahap penanganan/workflow yang sudah ada. Tingkat yang mewajibkan RTP menolak usulan non-mitigasi. |
| Pemantauan dan reviu | Tampilan baca-saja detail Kasub, tab tahap, dan riwayat review | Peta mengikuti scope asesmen; data historis tidak dihitung ulang. Tindakan review tetap mengikuti workflow yang ada. |

## Hubungan dengan konsep manajemen risiko

Penyajian tiga tahap membantu membedakan kondisi risiko saat ini, rencana perlakuan, dan hasil aktual. Istilah perlakuan menggunakan padanan umum *modify*, *retain*, *avoid*, dan *share*. Penilaian dan kriteria penerimaan merupakan data metode yang disetujui pemilik produk, bukan nilai universal atau kutipan norma Permenhan/ISO.

Peta risiko menggunakan matriks dan tingkat dari versi metode yang terikat pada asesmen. Backend menjadi sumber skor dan batas tingkat; UI hanya menyajikan data tersebut. Endpoint peta mengikuti scope asesmen yang sudah ada dan tidak membuat tabel peta terpisah karena jumlah sel diturunkan dari penilaian tersimpan.

## Batas implementasi dan migrasi

- Perubahan skema bersifat aditif dan nullable/defaulted agar data legacy tidak ditulis ulang.
- Versi metode legacy tetap memakai konfigurasi matriks yang sudah tersimpan.
- Migration `backend/database/migrations/2026_11_09_000001_add_versioned_risk_assessment_criteria.php` hanya ditulis; migration dan seeder tidak dijalankan pada database aplikasi.
- Detail rentang kemungkinan, deskripsi dampak, dan representasi pimpinan Unit Pemilik Risiko belum diputuskan, sehingga tidak dipatok oleh implementasi.
