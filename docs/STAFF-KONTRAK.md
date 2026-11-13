# Kontrak Backend STAFF

Status: audit statis Langkah A, 13 November 2026. Tidak ada kode aplikasi atau
data diubah. `php` tidak tersedia pada PATH lingkungan ini; karena itu
`php artisan route:list --path=api --json` dan `php artisan test` **belum
terverifikasi melalui eksekusi**. Temuan berikut berasal dari rute, Request,
Policy, service, workflow, dan tes yang disebutkan di bawah.

## Ringkasan keputusan yang terverifikasi

- Persona penyusun yang diizinkan membuat dan mengubah asesmen adalah **STAFF**
  yang merupakan pembuat asesmen serta berada dalam scope unit STAFF. KASUB
  dapat menjadi pembuat pada data lama/tes dan workflow memiliki perlakuan
  khusus agar ia tidak mereview sendiri, tetapi `AsesmenPolicy::create()` tidak
  memberi KASUB hak membuat baru. Sumber: `backend/app/Policies/AsesmenPolicy.php`,
  `backend/app/Services/AsesmenService.php`,
  `backend/tests/Feature/AsesmenWorkflowTest.php`.
- STAFF tidak dapat mereview asesmen: `ReviewScope` dipanggil untuk peran
  KASUB/KABID/KASATKER; tes menegaskan STAFF menerima 403. Sumber:
  `backend/app/Workflow/AsesmenWorkflow.php`,
  `backend/tests/Feature/AsesmenWorkflowTest.php`.
- SOP, versi SOP, dan kontrol internal sudah write-protected untuk STAFF:
  `KepatuhanPolicy::write()` hanya menerima ADMIN_SATKER untuk ketiganya.
  Sumber: `backend/app/Policies/KepatuhanPolicy.php`.
- Aset serta penugasan aset dapat dibaca STAFF dalam scope, tetapi tulis
  mensyaratkan ADMIN_SATKER atau KASUB. Sumber:
  `backend/app/Policies/AsetPolicy.php`, `backend/app/Services/AsetService.php`.
- Keputusan terbaru mengecualikan SOA dari web STAFF. Namun backend yang ada
  masih memberi STAFF write pada `soa`, `soa-versi`, `soa-detail`, dan
  `soa-benchmark` di scope. Jangan tampilkan jalur itu dan jangan ubah backend
  pada putaran ini. Sumber: `backend/app/Policies/KepatuhanPolicy.php`.

Semua endpoint berikut berada di bawah `auth:sanctum` dan `account.active`.
Daftar rute hasil artisan belum dapat dibandingkan karena prasyarat PHP gagal.

## Endpoint yang relevan

"Filter/sort" berarti allowlist pada service/controller; seluruh list menerima
`page`, `per_page` (maksimum 100), `sort`, dan `filter` bila Request terkait
memvalidasinya. Tidak ada parameter `include` pada kelompok berikut.

| Kelompok | Metode dan path | Controller@aksi | Hak STAFF / filter-sort |
| --- | --- | --- | --- |
| Asesmen | `GET,POST /api/asesmen`; `GET,PATCH /api/asesmen/{id}` | `AsesmenController@index/store/show/update` | Baca hanya asesmen yang dibuat sendiri; create/write pembuat STAFF dalam scope. Filter asesmen: id, unit_id, periode_id, seri_asesmen_id, status, nomor_versi, dibuat_oleh, versi_metode_id. |
| Risiko/aset snapshot | `GET,POST /api/asesmen/{assessment}/risiko`; `GET,PATCH,DELETE /api/risiko/{id}`; `GET,POST /api/asesmen/{assessment}/aset`; `GET,PATCH,DELETE /api/asesmen-aset/{id}` | `AsesmenController` | Tulis hanya asesmen DRAFT milik STAFF; filter risiko/aset snapshot ada di service. |
| Penilaian/usulan | `GET,POST /api/risiko/{risk}/penilaian`; `GET /api/penilaian/{id}`; `GET,POST /api/risiko/{risk}/usulan-keputusan`; `GET /api/usulan-keputusan/{id}` | `AsesmenController` | Tulis mengikuti `asesmen.write`; filter penilaian: id, risiko_id, matriks_id, tahap, dinilai_oleh; usulan: id, penilaian_id, keputusan. |
| Peta/riwayat/review | `GET /api/asesmen/{id}/peta-risiko`, `/riwayat-versi`, `/review` | `AsesmenController@riskMap`; `AsesmenWorkflowController@history/reviews` | Baca dalam scope; riwayat/review belum membatasi assessor ke pembuat pada controller, sehingga bergantung pada scope. Review tidak dapat dimutasi STAFF. |
| Workflow | `POST /api/asesmen/{id}/ajukan`, `/buat-revisi`, `/selesai-penanganan`, `/ajukan-residual` | `AsesmenWorkflowController@action` | Ajukan/revisi mengikuti `asesmen.write`; selesai penanganan juga boleh PIC STAFF dalam scope. `/review` bukan aksi STAFF. |
| Rencana/progres | `GET,POST /api/asesmen/{assessment}/rencana-penanganan`; `GET,PATCH /api/rencana-penanganan/{id}`; `GET,POST /api/rencana-penanganan/{plan}/progres`; `GET /api/progres/{id}` | `PenangananController@index/store/show/update` | Daftar berbasis asesmen dalam scope, bukan PIC. Tulis rencana = penyusun; progres = ADMIN_SATKER atau STAFF yang pegawai-nya PIC dan unitnya scope. Filter rencana: id,kode,status,pic_id,target_selesai; progres: id,status,dilaporkan_oleh,dilaporkan_pada. |
| Bukti asesmen/progres | `POST /api/penilaian/{id}/bukti`; `POST /api/progres/{id}/bukti` | `PenangananController@store` | Menautkan dokumen-versi FINAL; penilaian mengikuti penyusun, progres mengikuti PIC. Tidak ada GET bukti khusus. |
| Dokumen/lampiran | `GET /api/dokumen`, `/dokumen/{id}`, `/dokumen/{document}/versi`, `/dokumen-versi`, `/dokumen-versi/{id}`, `/file-lampiran`, `/file-lampiran/{id}`, `/file-lampiran/{id}/unduh` | `DokumenController@index/show/download` | STAFF dapat baca dalam scope. Backend juga mengizinkan STAFF membuat dokumen, versi, finalisasi, dan unggah—ini lebih luas dari UI baca-saja yang diminta, maka jangan ekspos tombol tersebut kecuali alur bukti memang memerlukannya. Filter: dokumen kode/judul/jenis/unit/dibuat_oleh; versi id/dokumen/nomor/status/dibuat_oleh; file id/pengunggah/nama/ekstensi/status. |
| Aset | `GET /api/aset`, `/aset/{id}`, `/aset/pilihan`, `/penugasan-aset`, `/penugasan-aset/{id}` | `AsetController@index/show/options` | Read-only untuk STAFF; filter aset dan penugasan ada di `AsetService::listing()`. |
| Kontrol/SOP | `GET /api/kontrol-internal`, `/kontrol-internal/{id}`, `/sop`, `/sop/{id}`, `/sop/{parent}/versi`, `/sop-versi/{id}` serta tautan GET yang terdaftar | `KepatuhanController@index/show/links` | Baca dalam scope. POST/PATCH/DELETE ditolak Policy untuk STAFF. Filter/sort memakai fillable model (`KepatuhanController@index`). |
| SOA (tidak untuk UI STAFF) | `GET/POST/PATCH/DELETE` pada `/api/soa`, `/soa-versi`, `/soa-detail`, `/soa-benchmark` serta `/ajukan` | `KepatuhanController` | Ada secara backend; jangan gunakan untuk UI STAFF sesuai keputusan. |

## Hak tulis asesmen dan pembekuan

| Resource/status | STAFF boleh |
| --- | --- |
| Asesmen/risko/aset-snapshot DRAFT | Buat, ubah, hapus risiko/snapshot, penilaian, usulan, rencana; hanya pembuat dan selama periode aktif. |
| DRAFT → ajukan | Ya, bila lengkap (lihat bawah). |
| MENUNGGU_KASUB, MENUNGGU_KABID | Tidak; hanya reviewer yang tepat dapat bertindak. |
| DITOLAK | Tidak dapat mengubah versi lama; pembuat boleh `buat-revisi`, menghasilkan DRAFT baru. |
| PENANGANAN/DIREVIU | STAFF PIC dapat melapor progres dan menautkan bukti; penyusun/PIC dapat menyelesaikan penanganan bila syarat terpenuhi. |
| RESIDUAL_DRAFT | Konfigurasi menyebut penyusun dapat `ajukan-residual`; implementasi workflow yang aktif juga menangani status `DIREVIU` untuk aksi ini. Ketidaksesuaian ini perlu diuji setelah PHP tersedia. |
| DISAHKAN atau `dibekukan_pada` | Tidak dapat diubah. `dibekukan_awal_pada` juga mengunci bagian awal. |
| SOP/kontrol internal/aset | STAFF hanya baca pada UI; Policy menolak tulis SOP/kontrol/aset. |

Kode penolakan penting: `asesmen_bukan_draft`, `periode_tidak_berjalan`,
`aset_sudah_direkam`, `aset_beda_asesmen`, `snapshot_dipakai`,
`risiko_penanganan_tidak_valid`, `bukti_dibekukan`, `bukti_sudah_ada`, dan
`transisi_tidak_valid` (BusinessRuleException → bentuk 409 menurut kontrak
proyek). Validasi kelengkapan melempar 422 `errors.kelengkapan`.

## Syarat ajukan, penolakan, dan revisi

`ajukan` mensyaratkan metode dibekukan, minimal satu snapshot aset dan risiko,
setiap risiko bertaut ke aset, memiliki penilaian SAAT_INI serta usulan. Usulan
MODIFIKASI/MITIGASI juga memerlukan TARGET_SETELAH dengan skor lebih rendah dan
minimal satu rencana penanganan. Sumber:
`backend/app/Workflow/AsesmenWorkflow.php#assertInitialComplete`.

Penolakan reviewer membutuhkan catatan (`catatan_wajib`, 409). Penyusun dan
pembuat penilaian tak dapat mereview asesmen sendiri (`pemisahan_tugas`). Unit
SATKER melewati KASUB dan KABID; BIDANG/BAGIAN melewati KASUB. Sumber:
`backend/config/alur_review.php`, `backend/app/Workflow/AsesmenWorkflow.php`.

`DITOLAK -> buat-revisi` membuat versi nomor berikutnya, mengisi
`versi_sebelumnya_id`, berstatus DRAFT, lalu menyalin snapshot aset, risiko,
penilaian, usulan, rencana, dan tautan yang diremap (dibuktikan oleh tes
workflow). Versi ditolak tetap tidak dapat diubah. Penolakan residual meminta
`kembali_ke` bernilai `PENANGANAN` atau `RESIDUAL_DRAFT`; ketiadaannya memberi
`kembali_ke_wajib`. Catatan reviewer tersedia melalui GET review, tetapi respons
belum menambahkan label nama reviewer.

## Scope dan tugas saya

`ScopeUnit` mengumpulkan role aktif, menghitung unit efektif beserta turunan,
dan super admin melihat semua unit. Query detail memakai `findOrFail` setelah
scope sehingga objek lintas scope menjadi 404. Aksi yang menemukan objek dalam
scope lalu gagal policy menghasilkan 403. Akun multi-role menggunakan role
aktif; beberapa service secara khusus memilih scope ADMIN_SATKER.

PIC ditentukan oleh `USERS.pegawai_id == RENCANA_PENANGANAN.pic_id`; pegawai
aktif harus memiliki penempatan aktif. Penugasan aset hanya dibaca melalui aset
dalam scope; tidak ada filter "tugas saya" pada penugasan. Sumber:
`backend/app/Services/ScopeUnit.php`, `PenangananService.php`,
`AsetService.php`.

Belum ada `GET /api/tugas-saya`, filter `pic_saya`, atau list rencana lintas
asesmen. Daftar asesmen STAFF sudah otomatis `dibuat_oleh = actor`; filter
status tersedia. Daftar rencana hanya bersarang per asesmen sehingga dashboard
PIC tidak dapat dibuat efisien/otoritatif dengan endpoint saat ini. **Celah B1
prioritas tertinggi:** endpoint hitungan server dan/atau filter PIC sesuai
usulan master prompt; jumlahnya harus setara `meta.total` daftar yang sama.

## Dokumen dan bukti

Alur bukti: STAFF membuat/menggunakan DOKUMEN → DOKUMEN_VERSI, memfinalkan
versi, unggah FILE_LAMPIRAN (awal PENDING), lalu endpoint bukti hanya menerima
`dokumen_versi_id` yang FINAL (`DokumenService::finalVersion`). Endpoint unduh
menolak selain status `AMAN` dengan `lampiran_belum_aman`; scope dokumen tetap
diperiksa sebelum unduh. Tidak ada GET relasi bukti penilaian/progres yang
khusus—**celah B2** untuk menampilkan bukti yang telah tertaut.

Validator file membatasi ukuran `LAMPIRAN_MAX_KB` (default 10240 KB).
Konfigurasi hanya mengizinkan PDF, PNG, JPG/JPEG, TXT berdasarkan MIME; perlu
uji eksekusi untuk memastikan penerapan pemeriksaan ekstensi/MIME di jalur
upload. Jangan pernah tampilkan `storage_key`, checksum, atau lokasi internal.
Sumber: `backend/config/lampiran.php`, `DokumenRequest.php`,
`DokumenService.php`.

## Respons dan label

List memakai `{data, meta}` dari Laravel paginator; detail `{data: {...}}`.
Asesmen menyertakan field tabel mentah (mis. `unit_id`, `periode_id`,
`versi_metode_id`, `dibuat_oleh`, status, versi, timestamps); risiko dan
penilaian serupa ID relasi. Peta risiko menambahkan metode, matriks, aset,
risiko, dan nilai hitung. Rencana/progres memakai ID PIC/pelapor. Tidak ada
`unit_nama`, `periode_nama`, `versi_metode_nama`, `dibuat_oleh_nama`,
`pic_nama`, `dilaporkan_oleh_nama`, `reviewer_nama`, atau
`pemilik_risiko_nama`. **Celah B2 prioritas kedua:** label nama read-only,
tanpa NIP/email dan tanpa N+1.

## Konkurensi, seeder, dan pendaftaran

Mutasi penting mengunci baris dengan `lockForUpdate`, tetapi request tidak
membawa `updated_at`, versi optimistik, atau ETag. Dua penyusun terhadap draft
yang sama masih berisiko last-write-wins setelah transaksi bergiliran. Ini
**celah B4**, tidak diubah tanpa persetujuan.

Hanya `RoleSeeder` dan `SimrisSeeder` terdaftar di `DatabaseSeeder`; tidak ada
`KasubDemoSeeder` atau `StaffDemoSeeder`. Data lokal dengan dua satker, status
STAFF, PIC/progres/bukti sebagaimana diminta **belum ada/ belum terverifikasi**.
Seeder B3 kelak harus terpisah, lokal-idempoten, dan tidak terdaftar global.

Pendaftaran mandiri tidak memilih role (`RegisterRequest` hanya data akun).
Namun endpoint terautentikasi `POST /api/v1/pengajuan-role` membolehkan
pengajuan STAFF, KASUB, KABID, atau KASATKER—jadi pendaftar dapat mengajukan
lebih dari STAFF. Daftar, setuju, dan tolak approval membatasi
`unit_id` pada `idsForRoles(..., ADMIN_SATKER)`, sehingga admin satker hanya
melihat/memutus pengajuan satkernya. Sumber:
`backend/app/Http/Controllers/Api/V1/AccountApprovalController.php`.

## Prioritas celah backend

1. B1: daftar/filter/hitungan "Tugas saya" server untuk asesmen pembuat sendiri
   dan rencana PIC sendiri, termasuk lewat target.
2. B2: label display aman dan GET relasi bukti agar dashboard/detail tidak
   menebak atau melakukan lookup sensitif.
3. B3: seeder lokal STAFF yang aman dan eksplisit (belum tersedia).
4. B4: penjaga konkurensi optimistik, hanya setelah persetujuan.
5. Sinkronkan/ujikan perbedaan status residual (`RESIDUAL_DRAFT` config versus
   `DIREVIU` pada cabang workflow) sebelum frontend mengandalkannya.

Tidak ada perubahan pada konfigurasi keamanan, middleware, Policy/Gate,
ScopeUnit/ReviewScope, atau alur review.
