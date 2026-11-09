# Audit Form Risk Assessment (Langkah A - Baca-Saja)

**Status:** Langkah A selesai. Berhenti di sini dan menunggu keputusan pemilik produk sebelum Langkah B/C.  
**Cakupan:** Migration, model, request, service, workflow, route, dan form Kasub yang tersedia di worktree. Query database hanya untuk agregat dan metadata, tanpa memilih data pribadi.  
**Sumber regulasi:** Materi yang diberikan menyatakan berkas Permenhan No. 17 Tahun 2021 yang tersedia hanya batang tubuh Pasal 1-22; Lampiran I dan II tidak tersedia. Angka atau kriteria yang belum disetujui di bawah adalah usulan produk, bukan ketentuan yang diklaim berasal dari Permenhan. ISO/IEC 27005 dirujuk secara umum, tanpa kutipan teks standar.

Dokumen `master-prompt-kasub.md` dan `KASUB-KONTRAK.md` yang dirujuk tidak ditemukan pada workspace. Perbandingan dengan kedua dokumen itu karena itu belum dapat dilakukan.

## 1. Skema dan alur data yang ditemukan

Sumber skema runtime yang diperiksa adalah [migration dasar](../backend/database/migrations/2026_10_12_072709_create_simris_domain_tables.php). Migration tersebut berstatus **Ran** pada database `simrisoke`.

| Entitas | Struktur dan perilaku relevan |
|---|---|
| `VERSI_METODE_RISIKO` | `nama`, `versi`, `referensi`, `dibekukan`. Tidak memiliki kolom `dibekukan_pada` pada migration dasar/runtime. |
| `SKALA_KEMUNGKINAN` | `versi_metode_id`, `nilai`, `label`, `label_persentase`, `label_frekuensi`, `kriteria`; nilai unik per versi metode. |
| `SKALA_DAMPAK` | `versi_metode_id`, `nilai`, `label`, `kriteria`; nilai unik per versi metode. |
| `AREA_DAMPAK` / `KRITERIA_DAMPAK` | Area dampak global; kriteria per skala dampak, area, dan arah `P`/`N`. |
| `TINGKAT_RISIKO` | `versi_metode_id`, `kode`, `nama`, `warna`; batas skor/kriteria penerimaan/RTP tidak ada di migration dasar. |
| `MATRIKS_RISIKO` | Menyimpan pasangan skala, `tingkat_risiko_id`, `skor`, dan `rekomendasi_keputusan`; pasangan skala unik per versi metode. Ini adalah data matriks yang terikat ke versi metode. |
| `ASESMEN_RISIKO` | Mengikat asesmen ke `versi_metode_id`, periode, unit, pembuat, tujuan, ruang lingkup dan nomor versi. Status disimpan sebagai string. `dibekukan_awal_pada` dan `dibekukan_pada` adalah kolom asesmen, bukan kolom versi metode. |
| `RISIKO` | Menyimpan ancaman, kerentanan, area dampak, pemilik risiko, skenario, uraian kerentanan/dampak, dan arah dampak. Migration dasar belum memuat konteks atau konsekuensi K/I/A. |
| `ASESMEN_ASET` / `RISIKO_ASET` | Snapshot aset per asesmen dan pivot yang menautkan snapshot ke risiko sudah ada. Pivot tidak membawa `asesmen_id`; kesesuaian asesmen perlu dijaga oleh aplikasi. |
| `PENILAIAN_RISIKO` | Menyimpan `matriks_id`, penilai, tahap, alasan kemungkinan/dampak, dan kesimpulan. Tidak menyimpan nilai kemungkinan/dampak atau skor tersendiri. Tahap adalah string yang divalidasi aplikasi: `SAAT_INI`, `TARGET_SETELAH`, `AKTUAL_SETELAH`. |
| Keputusan dan RTP | `USULAN_KEPUTUSAN` serta `KEPUTUSAN_RISIKO` menyimpan keputusan sebagai string. `RENCANA_PENANGANAN`, `RISIKO_PENANGANAN`, dan `PROGRES_PENANGANAN` tersedia untuk rencana, kaitan risiko, dan progres. Status/tahap juga divalidasi aplikasi, bukan enum database. |

Nilai usulan yang diterima `AsesmenRequest` adalah `MITIGASI`, `TERIMA`, `HINDARI`, `ALIHKAN`. Nilai ini tidak diganti nama. Nilai rekomendasi yang tersimpan di 25 sel metode aktif hanya `MITIGASI` dan `TERIMA`; belum ada asesmen atau penilaian pada versi itu.

## 2. Skala, matriks, pembekuan, dan data aktual

Query agregat `SELECT` saja dijalankan pada database terkonfigurasi `simrisoke`. Hasil untuk versi metode aktif:

| ID / versi metode | Beku | Kemungkinan | Dampak | Tingkat risiko | Sel matriks | Asesmen | Penilaian |
|---|---:|---:|---:|---:|---:|---:|---:|
| 1 / `Metode contoh 5x5`, versi `1` | Ya | 5 (1-5) | 5 (1-5) | 3 | 25 | 0 | 0 |

Data metode aktif yang terbaca:

- Label kemungkinan masih `Kemungkinan 1` sampai `Kemungkinan 5`; persentase tersimpan `20%` sampai `100%`; label frekuensi berupa contoh, bukan rentang frekuensi yang disahkan.
- Label dampak masih `Dampak 1` sampai `Dampak 5`.
- Pemetaan sel yang tersimpan: `RENDAH` 8 sel (skor 1-4), `SEDANG` 11 sel (5-12), `TINGGI` 6 sel (15-25). Nilai ini merupakan isi versi metode aktif, bukan batas universal.
- Satu area dampak memiliki 10 baris kriteria: lima arah `P` dan lima arah `N`.
- Versi metode dibekukan dengan flag `dibekukan`. `MetodeRisikoService` memvalidasi isi metode sebelum menyimpan flag tersebut; observer mencegah perubahan pada anak metode setelah beku, dan asesmen baru mensyaratkan metode beku. Tidak ada timestamp pembekuan versi metode pada schema runtime.
- `dibekukan_awal_pada` pada `ASESMEN_RISIKO` diisi saat review awal selesai; `dibekukan_pada` diisi saat asesmen disahkan atau ditolak. Keduanya mengunci bagian/status asesmen, bukan cara membekukan versi metode.

### Perilaku skor: temuan dan selisih terhadap sumber data matriks

- Migration menyimpan `skor`, tingkat risiko, warna, dan rekomendasi pada setiap baris `MATRIKS_RISIKO`. Penilaian lama merujuk ke baris tersebut melalui `matriks_id`.
- Pada kode worktree saat ini, `prepareCell()` di [MetodeRisikoService.php](../backend/app/Services/MetodeRisikoService.php) menghitung skor dengan perkalian saat menyiapkan sel. `fromCell()` di [HitungRisiko.php](../backend/app/Services/HitungRisiko.php) juga menghitung ulang skor dan membandingkannya dengan skor tersimpan. Jalur profil baru memetakan nilai kemungkinan/dampak ke sel, tetapi masih melewati hitung ulang/perbandingan tersebut.
- [MatriksGrid.tsx](../frontend/src/components/MatriksGrid.tsx) menampilkan skor tersimpan bila tersedia, namun memiliki fallback perkalian bila data sel tidak ada.
- Dengan demikian, syarat pada materi terbaru agar skor/tingkat/warna/rekomendasi bersumber dari baris matriks dan bukan dihitung ulang **belum dipenuhi sepenuhnya oleh kode saat ini**. Temuan ini dicatat untuk keputusan/implementasi berikutnya; tidak ada kode yang diubah pada audit.

## 3. Form, validasi, dan review Kasub

- [BuatAsesmenPage.tsx](../frontend/src/features/kasub/BuatAsesmenPage.tsx) memberi Kasub pilihan unit dalam scope, periode yang sedang berjalan, dan metode beku; halaman mengirim pembuatan draft.
- [AsesmenDetailPage.tsx](../frontend/src/features/kasub/AsesmenDetailPage.tsx) memuat detail asesmen melalui `GET /api/asesmen/{id}/peta-risiko`. Kode worktree sudah memiliki endpoint peta dan tampilan matriks/detail, sehingga hal tersebut bukan fitur yang belum ada sama sekali. Perubahan berikutnya perlu menilai dan memperbaiki implementasi yang ada, bukan mengasumsikan UI/endpoint masih kosong.
- Form risiko yang ada memuat ancaman, kerentanan, area dampak, pemilik, skenario, uraian, arah dampak, dan tautan snapshot aset. Kode worktree juga memuat field konteks dan konsekuensi K/I/A serta pilihan konteks untuk profil baru; migration yang menambah kolom tersebut masih berstatus **Pending** sehingga field itu belum ada pada schema runtime.
- Form penilaian metode lama mengirim `matriks_id`. Jalur profil baru mengirim integer kemungkinan/dampak 1-5, menampilkan pratinjau dari metode, dan menampilkan galat API per field. Validasi 1-5 pada backend berlaku untuk profil baru. Klien tetap bukan sumber otoritatif.
- [AsesmenRequest.php](../backend/app/Http/Requests/AsesmenRequest.php) mengondisikan field konteks, konsekuensi K/I/A, aset, dan nilai penilaian pada `profil_penilaian`. [PenilaianService.php](../backend/app/Services/PenilaianService.php) pada profil baru menolak opsi non-`MITIGASI` bila tingkat sel menyatakan `perlu_rtp`; pemeriksaan ini bergantung pada schema migration yang masih pending.
- [alur_asesmen.php](../backend/config/alur_asesmen.php), [alur_review.php](../backend/config/alur_review.php), dan [AsesmenWorkflow.php](../backend/app/Workflow/AsesmenWorkflow.php), bersama policy dan tes, menunjukkan Kasub termasuk role yang diizinkan membuat asesmen. Jika Kasub adalah pembuat, pengajuan awal/residual melewati review dirinya sendiri menuju Kabid. Sumber kebutuhan menyebut STAFF sebagai penyusun dan Kasub/Kabid/KASATKER sebagai peninjau; ini berbeda dari perilaku yang berjalan dan perlu keputusan produk sebelum alur diubah.
- Status runtime yang dipakai lebih banyak daripada status ringkas pada ERD lama: antara lain `DRAFT`, `MENUNGGU_KASUB`, `MENUNGGU_KABID`, `AWAL_SELESAI`, `PENANGANAN`, `RESIDUAL_DRAFT`, `VERIFIKASI_KASUB`, `VERIFIKASI_KABID`, `MENUNGGU_KASATKER`, `DISAHKAN`, `DITOLAK`, dan `KEMBALI`.

## 4. Selisih ERD, migration, dan worktree

1. [erd-v3.mmd](./erd-v3.mmd) dan [simris_v3_erd_revisi.mmd](./simris_v3_erd_revisi.mmd) berbeda; keduanya tidak boleh dianggap sebagai satu definisi schema.
2. Skema dasar sudah mempunyai skala, tingkat, kriteria dampak, matriks 25 sel, snapshot aset, dan relasi `PENILAIAN_RISIKO.matriks_id`.
3. [Migration kriteria asesmen berversi](../backend/database/migrations/2026_11_09_000001_add_versioned_risk_assessment_criteria.php) berstatus **Pending** pada database. Migration itu menambah `profil_penilaian`, batas dan kriteria penerimaan/RTP pada tingkat risiko, uraian dampak K/I/A/kepatuhan/operasional, konteks/konsekuensi risiko, dan alasan perubahan level. Migration tersebut tidak dijalankan dalam audit.
4. Karena migration pending, fitur kode yang membaca kolom tambahannya belum dapat dianggap aktif pada database runtime. Keputusan apakah kolom yang sudah ditambahkan pada worktree dipertahankan, diselaraskan dengan struktur matriks yang sudah ada, atau disederhanakan harus dibuat sebelum migration dijalankan.
5. ERD revisi mencantumkan `dibekukan_pada` untuk versi metode, tetapi migration dasar dan model runtime memakai flag `dibekukan` tanpa timestamp itu. ERD juga perlu dibedakan dari schema yang sudah benar-benar berjalan.

## 5. Peta kesenjangan

| Area | Sudah ada | Kesenjangan / perubahan yang perlu diputuskan |
|---|---|---|
| Konteks Permenhan | Tujuan, ruang lingkup, dan field konteks pada kode untuk profil baru | Field konteks belum ada di schema runtime; perlu persetujuan kategori dan status migration. |
| Asset -> ancaman -> kerentanan -> dampak | Entitas aset/snapshot, ancaman, kerentanan, area dampak, dan relasi risiko-aset tersedia | Perlu pastikan alur form dan validasi mengikat aset ke snapshot asesmen yang benar; tidak ada backfill otomatis. |
| Skala dan matriks | Lima nilai skala, 25 pasangan, skor/tingkat/warna/rekomendasi tersimpan per sel metode | Isi aktif masih contoh/generik. Kode juga menghitung ulang skor sehingga belum konsisten dengan syarat bahwa baris matriks adalah sumber nilai. |
| Kriteria dampak | Kriteria per dampak, area, dan arah `P`/`N` tersedia; data contoh ada | Deskriptor dan arti operasional tiap kriteria belum disahkan. Deskripsi K/I/A tambahan ada di migration pending, bukan schema runtime. |
| Penerimaan risiko / RTP | Opsi keputusan, rencana dan progres tersedia; kode profil baru memiliki flag RTP/penerimaan | Batas serta kriteria penerimaan tidak ada di schema dasar; definisi final dan pengaitan level-keputusan perlu disetujui. |
| Evaluasi dan tahap | Nilai tahap lama tersedia dan dipakai workflow | Tambahkan label tampilan tanpa mengubah nilai tahap. Sepakati label target dan aktual. |
| Peta risiko | Route dan komponen detail peta sudah ada di worktree | Verifikasi sumber skor/level harus memakai baris matriks; jangan mengasumsikan pekerjaan baru dari nol. |
| Peran penyusun | STAFF dan KASUB sama-sama diizinkan membuat asesmen | Putuskan konflik antara spesifikasi STAFF-penyusun dan alur Kasub-pembuat yang sudah berjalan; jangan mengubah workflow sebelum disetujui. |
| Laporan tahunan | Tidak ditemukan pada cakupan form/review ini | Tetap di luar lingkup; format Lampiran II belum tersedia. |

## 6. Keputusan pemilik produk yang masih diperlukan

Tidak ditemukan persetujuan pemilik produk yang dapat dijadikan dasar implementasi dalam instruksi terbaru. Usulan awal berikut bukan keputusan final dan tidak boleh dipaksakan ke metode lama:

| Keputusan | Usulan awal untuk ditinjau |
|---|---|
| Deskriptor kemungkinan | 1 Sangat jarang; 2 Jarang; 3 Mungkin; 4 Sering; 5 Hampir pasti. Rentang probabilitas/frekuensi tiap tingkat harus diisi dan disahkan pemilik produk. |
| Deskriptor dampak | 1 Tidak signifikan; 2 Minor; 3 Moderat; 4 Mayor; 5 Katastropik. Uraian per dimensi K/I/A, kepatuhan, dan operasional harus disediakan pemilik produk; audit ini tidak mengarangnya. |
| Batas tingkat dan penerimaan | Usulan: skor 1-4 Rendah, 5-9 Sedang, 10-16 Tinggi, 17-25 Sangat Tinggi; Rendah dapat diterima, Sedang memerlukan persetujuan pemilik risiko, Tinggi/Sangat Tinggi memerlukan RTP. Harus disahkan dan menjadi data versi metode; jangan menggantikan pemetaan sel metode lama. |
| Sumber nilai otoritatif | Ikuti struktur yang sudah ada: nilai skor, tingkat, warna, dan rekomendasi dibaca dari baris matriks versi metode; hentikan penghitungan ulang oleh server maupun klien. |
| Opsi perlakuan | Pertahankan nilai backend: `MITIGASI` -> modify, `TERIMA` -> retain, `HINDARI` -> avoid, `ALIHKAN` -> share/bagikan. Konfirmasi label UI untuk `ALIHKAN`. |
| Label tahap | `SAAT_INI` = risiko dengan kontrol yang ada; `TARGET_SETELAH` = target setelah RTP; `AKTUAL_SETELAH` = aktual setelah penanganan. Nilai enum/string lama tetap dipertahankan. |
| Penyusun dan reviewer | Putuskan apakah STAFF harus menjadi penyusun utama sesuai kebutuhan atau apakah hak Kasub membuat asesmen tetap diperlukan. Jika dipertahankan, jelaskan jalur pengajuan tanpa self-review. |
| Target lebih tinggi | Tentukan apakah target yang lebih tinggi dari kondisi saat ini boleh dibuat dengan alasan tercatat dan siapa yang menyetujuinya. Usulan awal pada prompt: boleh dengan alasan eksplisit. |
| Pemilik risiko | Tentukan apakah `pemilik_risiko_id` yang sekarang menunjuk pegawai sudah cukup mewakili pimpinan Unit Pemilik Risiko dan pemberi persetujuan risiko Sedang. |
| Struktur ERD/schema | Tetapkan ERD acuan serta putuskan nasib kolom pada migration Pending agar tidak menduplikasi struktur matriks/metode yang sudah ada. Jangan menjalankan migration sebelum persetujuan. |

## 7. Batas audit dan status tindakan

- Yang diubah pada Langkah A: hanya dokumen ini, untuk mengganti audit lama yang menyatakan keputusan telah disetujui dan implementasi berjalan.
- Yang diperiksa tanpa perubahan data: kode, migration, ERD, status migration, serta query agregat `SELECT` pada database `simrisoke`.
- Tidak ada migration atau seeder yang dijalankan. Tidak ada backfill, perubahan sumber aplikasi, tes, atau perubahan schema runtime.
- Tidak ada perubahan pada konfigurasi keamanan, middleware, Policy/Gate, `ScopeUnit`/`ReviewScope`, atau alur review.
- **Berhenti setelah Langkah A.** Langkah B/C dan migration menunggu keputusan pada bagian 6.
