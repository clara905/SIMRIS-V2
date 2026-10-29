# Usulan perubahan relasi SOP dan dokumen

**Status:** usulan untuk persetujuan produk dan pemilik data. Belum diterapkan; migration dan perubahan kode terkait tidak dibuat atau dijalankan.

## Usulan skema

1. Tambahkan `SOP.dokumen_id` sebagai nullable foreign key ke `DOKUMEN.id` pada tahap backfill, dengan unique constraint agar satu dokumen hanya terikat ke satu SOP. Setelah data lama dipetakan dan divalidasi, ubah kolom menjadi wajib.
2. Pastikan dokumen yang ditautkan memiliki `DOKUMEN.jenis = SOP`. Karena skema saat ini tidak memakai CHECK constraint, validasi jenis perlu ditegakkan oleh service/request; constraint database dapat diajukan terpisah jika standar database menyetujuinya.
3. Ubah `SOP_VERSI.nomor_versi` dari string menjadi integer, setara dengan `DOKUMEN_VERSI.nomor_versi`.
4. Validasi bahwa versi dokumen yang dipakai oleh versi SOP memiliki `dokumen_id` sama dengan `SOP.dokumen_id`, dan nomor versi SOP sama dengan nomor versi dokumennya.

## Rencana migrasi dan dampak data

- Lakukan pemeriksaan data sebelum migration: hitung SOP tanpa dokumen terkait, SOP dengan beberapa dokumen kandidat, versi dengan nomor yang bukan bilangan bulat, nomor duplikat, dan ketidaksesuaian dokumen/nomor versi.
- `SOP_VERSI.dokumen_versi_id` dapat mengidentifikasi dokumen kandidat untuk SOP yang sudah memiliki versi. SOP lama tanpa versi belum tentu dapat dipetakan otomatis; data tersebut memerlukan pemetaan dan keputusan pemilik data.
- Jika satu SOP memiliki beberapa `dokumen_id` kandidat, atau satu dokumen kandidat diklaim beberapa SOP, jangan memilih otomatis. Hentikan backfill baris tersebut dan minta pemilik data menentukan relasi yang benar.
- Backfill hanya sesudah laporan pemeriksaan ditinjau. Jalankan migration di lingkungan staging/salinan data terlebih dahulu; jangan menghapus dokumen atau versi lama sebagai bagian dari konversi.
- Ubah kolom versi ke integer hanya setelah semua nilai lolos pemeriksaan dan backup/rollback tersedia. Jika nilai lama tidak numerik, migration harus berhenti dengan laporan baris bermasalah, bukan mengubahnya menjadi angka tebakan.

## Perubahan aplikasi setelah persetujuan

- `KepatuhanRequest` perlu berhenti menerima `dokumen_versi_id` sebagai relasi bebas untuk pembuatan versi SOP; pengaitan dokumen harus mengikuti `SOP.dokumen_id`.
- `KepatuhanService` perlu memastikan dokumen berjenis SOP dan dimiliki unit yang sama, menetapkan nomor versi SOP berdasarkan aturan yang disetujui, serta menolak versi dokumen yang berbeda dokumen atau nomor versinya.
- Perbarui factory/fixture SOP dan tes untuk membuat dokumen berjenis SOP, menguji nomor yang cocok, dan menolak dokumen salah jenis/unit, relasi silang, serta nomor versi tidak cocok. Tambahkan tes untuk skenario backfill/legacy setelah kebijakan data disepakati.
- Perbarui kontrak API dan tipe/form frontend yang membuat SOP/SOP-versi; field dan bentuk payload dapat berubah. Koordinasikan rilis backend dan frontend agar form lama yang mengirim `dokumen_versi_id` tidak gagal tanpa penjelasan.

## Keputusan yang diperlukan

Setujui aturan pemetaan SOP lama tanpa versi, kebijakan untuk relasi kandidat yang ambigu, dan kapan kolom `dokumen_id` boleh diwajibkan. Sampai ada persetujuan, skema, service, request, fixture, dan frontend tetap tidak diubah untuk usulan ini.
