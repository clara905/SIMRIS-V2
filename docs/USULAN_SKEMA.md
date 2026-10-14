# Usulan skema backend

Tidak ada tambahan tabel atau kolom domain di luar ERD v3 dan perubahan yang telah disetujui pada bagian 3 serta 7a brief.

Keputusan pemetaan tipe dan selisih komentar ERD dicatat dalam `ASUMSI.md`. Perubahan skema lain, bila diperlukan pada tahap berikutnya, harus dicatat di sini dan menunggu keputusan pengguna sebelum diterapkan.

## Template pengaduan aset (14 Oktober 2026)

Template yang diterima berjudul LAPORAN PENGADUAN ASET, sedangkan endpoint laporan yang disepakati menghasilkan daftar risiko Lampiran 14 per asesmen. ERD belum memiliki entitas pengaduan untuk menyimpan nomor laporan, kategori/urgensi kejadian, status verifikasi, pengesahan, dan tindak lanjut pengaduan. Data tersebut tidak dapat dipetakan sebagai status atau penilaian risiko tanpa keputusan perubahan domain.

Integrasi template sebagai laporan pengaduan terisi ditunda sesuai aturan pengguna untuk berhenti jika selisih mengubah skema. Belum ada migrasi atau perubahan kontrak API. Keputusan yang diperlukan: formulir pengaduan kosong untuk dicetak, pemakaian kop/logo saja pada laporan risiko yang ada, atau modul pengaduan baru dengan revisi ERD terlebih dahulu.
