# SIMRIS — register arsip yang hidup

## Prinsip
Ruang kerja super admin berbahasa Indonesia. Informasi tersusun seperti register:
garis tipis, kolom yang sejajar, metadata tenang, dan aksi yang jelas.
Warna hangat hanya untuk tingkat risiko. Tidak ada gradien, kartu statistik
dekoratif, bayangan pada panel, atau animasi masuk otomatis.

## Token
Tinta #1B2A33; kertas #F4F6F7; lembar #FFFFFF; garis #D5DCE0;
teks kedua #5B6B75; Biru Cap #243E8F, hover #1B3175, tint #E6EBF8.
Galat form #A61B1B. Umpan balik lain memakai ikon dan garis netral/biru.
Risiko: #2E8B7A, #8FB339, #E3B23C, #E07B39, #C23B3B, selalu
disertai nama. Kontras teks dipilih berdasarkan luminans warna API.

IBM Plex Sans untuk UI; Condensed untuk tabel rapat; Newsreader hanya judul
halaman/dokumen. Font dibundel lokal. Ukuran 12, 13, 14, 16, 20, 28, 36 px;
angka tabular; monospace hanya untuk diff JSON. Paragraf maksimal 78ch.
Jarak: 4, 8, 12, 16, 24, 32, 48 px. Radius sel/chip 2 px,
kontrol 6 px, dialog/drawer 12 px. Bayangan hanya lapisan melayang.
Mode gelap disiapkan sebagai token; tidak diaktifkan sebelum audit kontras.

## Kerangka dan wireframe
Sidebar 236 px, dapat diciutkan. Header 68 px, halaman berjarak 32 px.
Di bawah 768 px navigasi berupa laci; drawer menjadi layar penuh.
Tabel dan matriks bergulir di wilayahnya, bukan menggulung seluruh halaman.

```text
BERANDA
| Navigasi | Beranda                             Pengguna / Keluar |
|          | Beranda                             Tanggal WIB       |
|          | Perlu ditindaklanjuti                                  |
|          | Jumlah | Masalah                     | Tinjau          |
|          | Ringkasan asesmen [batang bertumpuk + label]            |
|          | Aktivitas terakhir                                    |

PENGGUNA
| Navigasi | Pengguna                             Tambah pengguna  |
|          | Pencarian / status / kepadatan                         |
|          | Nama | Email | Role scope | Status | Drawer rincian   |
|          | ...                               | Form / riwayat   |

UNIT ORGANISASI
| Navigasi | Unit organisasi                      Tambah unit      |
|          | Pohon unit          | Rincian unit / anak / pegawai     |
|          | > Satker            | Pindah induk (pemilih pohon)      |
|          |   > Bidang          | Alasan larangan nonaktif         |

MATRIKS
| Navigasi | Metode / nama versi                  Duplikasi / Beku  |
|          | Penjelasan hanya-lihat               [Segel beku]      |
|          | Skala K | Skala D | Kriteria | Tingkat | Matriks        |
|          | Kemungkinan | skor + tingkat tiap sel                 |
|          |             | grid 5 x 5 (pilihan jamak)               |
|          |             +------------------------- Dampak          |
|          | Sel terpilih / pilih tingkat / Terapkan                |

LOG AUDIT
| Navigasi | Log audit                                              |
|          | Cari / entitas / aksi / tanggal                         |
|          | Waktu | Pelaku | Aksi | Entitas | Drawer rincian        |
|          |                                | Lama | Baru / penanda |
```

## Keputusan desain dan kritik awal
Mengganti pola dashboard kartu angka dengan register tindak lanjut agar
beranda langsung mengarahkan pekerjaan. Memakai sidebar putih bergaris,
bukan blok warna besar, untuk menjaga warna cap sebagai penanda tindakan.
Judul serif membedakan lembar kerja dari navigasi sans tanpa hiasan tambahan.
Segel dan matriks adalah elemen khas yang mempunyai makna domain.
Panel memakai garis dan ruang, bukan radius serta bayangan seragam.

## Interaksi dan aksesibilitas
Dialog/laci Radix menjaga fokus dan Escape. Konfirmasi pembekuan meminta
nama versi; semua tab terkunci setelah beku. Tombol terlarang tetap terlihat
dengan alasan. Monitoring selalu hanya-lihat. Fokus biru jelas; label form
terhubung, galat diumumkan. Pintasan /, ?, dan g lalu huruf tidak aktif saat
mengetik. Animasi segel hanya setelah pembekuan dan menghormati reduced motion.

## Gerbang verifikasi
Setiap tahap implementasi: typecheck, lint, Vitest, build, Playwright pada
1440/1024/390, kemudian pemeriksaan gambar. Tahap 0 adalah dokumen; baseline
aplikasi diperiksa sebelum fondasi. Hasil dan keterbatasan dicatat di
LAPORAN-FE.md. Tahap tidak dinyatakan selesai tanpa bukti.

## Tinjauan akhir

### Revisi futuristik

Permintaan berikutnya membawa visual ke gaya konsol: navigasi charcoal
#141B29 dengan teks terang, ruang kerja putih berpola grid halus, aksen merah,
judul IBM Plex Sans tebal, dan sudut panel asimetris. Header tetap terlihat
saat menggulir. Cahaya merah hanya menguatkan navigasi aktif dan hover aksi.
Warna gelap dibatasi pada navigasi dan pengantar login; ini bukan mode gelap
global. Fokus keyboard pada sidebar memakai merah muda terang agar jelas.
Aturan tipografi judul serif dalam rencana awal digantikan oleh revisi ini.

### Revisi merah–putih

Permintaan lanjutan pengguna mengganti aksen biru menjadi merah #B91C2C,
hover #941525, dan tint #FFF0F1 pada permukaan putih. Ketentuan warna biru
di rencana awal menjadi riwayat desain. Token aksen baru memiliki alias
kompatibilitas untuk komponen yang memakai nama token lama.
Navigasi aktif memakai merah solid dengan teks putih; garis merah menyatukan
header dan sidebar. Warna domain matriks tetap mengikuti tingkat risiko.
Filter prioritas beranda membatasi tindak lanjut berdasarkan kelompok.
Respons hover/fokus pada baris, tautan, tombol dan batang status membantu
pengguna mengenali elemen interaktif; transisi mengikuti reduced motion.

- Chip dekoratif “Tercatat” pada setiap aktivitas beranda dibuang; tautan
  “Lihat rincian” memberi tindakan yang nyata tanpa mengulang status seragam.
- Lebar sumbu matriks dibatasi agar lima kolom tetap terbaca pada tablet.
  Pada ponsel matriks dan tabel bergulir di dalam wilayahnya sendiri.
- Ikon pohon tidak boleh menyusut saat label membungkus. Galeri mencakup
  matriks kosong/terpilih/hanya-lihat, segel, diff, serta keadaan form.
- Font hanya memuat subset Latin yang diperlukan; halaman fitur dimuat
  melalui lazy route. Tidak ada permintaan font ke layanan eksternal.
- Warna status administratif tetap netral/biru. Kontras hitam/putih pada
  warna tingkat risiko dipilih berdasarkan luminansi, bukan asumsi warna.
- Mode gelap tetap belum aktif karena pasangan warnanya belum diaudit.
