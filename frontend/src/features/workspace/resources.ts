import {
  LayoutDashboard,
  ClipboardCheck,
  Boxes,
  ShieldCheck,
  FileText,
  UsersRound,
  Network,
  ContactRound,
  Grid2X2,
  BookOpen,
  CalendarDays,
  History,
  Paperclip,
} from 'lucide-react'
export const assessmentStatuses = [
  'DRAFT',
  'MENUNGGU_KASUB',
  'MENUNGGU_KABID',
  'PENANGANAN',
  'RESIDUAL_DRAFT',
  'VERIFIKASI_KASUB',
  'VERIFIKASI_KABID',
  'MENUNGGU_KASATKER',
  'DISAHKAN',
  'DITOLAK',
]
export type Resource = {
  key: string
  label: string
  endpoint: string
  columns: string[]
  statuses?: string[]
  statusField?: string
  admin?: boolean
  defaultFilter?: Record<string, unknown>
}
const resource = (
  key: string,
  label: string,
  columns: string[],
  extra: Partial<Resource> = {},
): Resource => ({ key, label, endpoint: '/api/' + key, columns, ...extra })
export const modules = [
  {
    path: '/',
    label: 'Ringkasan',
    group: 'Ruang kerja',
    icon: LayoutDashboard,
    description: 'Gambaran tata kelola risiko dalam lingkup akses Anda.',
    resources: [] as Resource[],
  },
  {
    path: '/monitoring',
    label: 'Asesmen risiko',
    group: 'Ruang kerja',
    icon: ClipboardCheck,
    description: 'Telusuri penilaian, status persetujuan, dan rencana penanganan.',
    resources: [
      resource('asesmen', 'Asesmen', ['id', 'tujuan', 'unit_id', 'nomor_versi', 'status'], {
        endpoint: '/api/monitoring/asesmen',
        statuses: assessmentStatuses,
      }),
    ],
  },
  {
    path: '/aset',
    label: 'Inventaris aset',
    group: 'Ruang kerja',
    icon: Boxes,
    description: 'Aset organisasi dan informasi hasil sinkronisasi sumber aset.',
    resources: [
      resource('aset', 'Semua aset', ['kode_aset', 'nama', 'unit_pemilik_id', 'kondisi', 'status_penilaian', 'status']),
      resource('aset-belum-dinilai', 'Aset belum dinilai', ['kode_aset', 'nama', 'unit_pemilik_id', 'kondisi', 'status_penilaian', 'status'], {
        endpoint: '/api/aset',
        defaultFilter: { perlu_ditinjau: true },
      }),
      resource('aset-dinilai', 'Aset sudah dinilai', ['kode_aset', 'nama', 'unit_pemilik_id', 'kondisi', 'status_penilaian', 'status'], {
        endpoint: '/api/aset',
        defaultFilter: { perlu_ditinjau: false },
      }),
    ],
  },
  {
    path: '/kepatuhan',
    label: 'Kontrol & kepatuhan',
    group: 'Ruang kerja',
    icon: ShieldCheck,
    description: 'Pantau Statement of Applicability, SOP, dan kontrol internal.',
    resources: [
      resource('soa', 'SOA', ['id', 'ruang_lingkup', 'unit_id', 'periode_id', 'standar_id']),
      resource('sop', 'SOP', ['kode', 'judul', 'unit_pemilik_id']),
      resource('kontrol-internal', 'Kontrol internal', ['kode', 'nama', 'unit_pemilik_id']),
    ],
  },
  {
    path: '/dokumen',
    label: 'Dokumen & bukti',
    group: 'Ruang kerja',
    icon: FileText,
    description: 'Dokumen pendukung, versi, dan hasil pemeriksaan lampiran.',
    resources: [
      resource('dokumen', 'Dokumen', ['kode', 'judul', 'jenis', 'unit_pemilik_id']),
      resource(
        'file-lampiran',
        'Lampiran',
        ['nama_asli', 'ekstensi', 'ukuran_byte', 'status_pemeriksaan'],
        {
          endpoint: '/api/monitoring/file-lampiran',
          statuses: ['PENDING', 'AMAN', 'DITOLAK'],
          statusField: 'status_pemeriksaan',
        },
      ),
    ],
  },
  {
    path: '/kelola/aset',
    label: 'Kelola aset & penugasan',
    group: 'Pengelolaan satker',
    icon: Boxes,
    roles: ['ADMIN_SATKER'],
    description: 'Ubah data lokal aset dan tetapkan penanggung jawab dalam satker Anda.',
    resources: [] as Resource[],
  },
  {
    path: '/kelola/dokumen',
    label: 'Kelola dokumen',
    group: 'Pengelolaan satker',
    icon: FileText,
    roles: ['ADMIN_SATKER'],
    description: 'Susun dokumen, versi, dan lampiran milik satker Anda.',
    resources: [] as Resource[],
  },
  {
    path: '/kelola/unit',
    label: 'Kelola unit organisasi',
    group: 'Pengelolaan satker',
    icon: Network,
    roles: ['ADMIN_SATKER'],
    description: 'Susun unit turunan dalam wilayah satker Anda.',
    resources: [] as Resource[],
  },
  {
    path: '/kelola/pegawai',
    label: 'Kelola pegawai',
    group: 'Pengelolaan satker',
    icon: ContactRound,
    roles: ['ADMIN_SATKER'],
    description: 'Kelola data pegawai dan penempatan pada unit satker.',
    resources: [] as Resource[],
  },
  {
    path: '/kelola/pengguna',
    label: 'Kelola pengguna',
    group: 'Pengelolaan satker',
    icon: UsersRound,
    roles: ['ADMIN_SATKER'],
    description: 'Kelola akun dan role operasional dalam wilayah satker.',
    resources: [] as Resource[],
  },
  {
    path: '/kelola/kepatuhan',
    label: 'Kelola SOP & kontrol internal',
    group: 'Pengelolaan satker',
    icon: ShieldCheck,
    roles: ['ADMIN_SATKER'],
    description: 'Susun draft SOP dan kontrol internal, lalu ajukan untuk ditinjau.',
    resources: [] as Resource[],
  },
  {
    path: '/metode-risiko',
    label: 'Metode risiko',
    group: 'Referensi',
    icon: Grid2X2,
    description: 'Versi metode dan parameter penilaian risiko yang berlaku.',
    resources: [
      resource('versi-metode-risiko', 'Versi metode', ['nama', 'versi', 'dibekukan']),
      resource('area-dampak', 'Area dampak', ['kode', 'nama']),
      resource('tingkat-risiko', 'Tingkat risiko', ['versi_metode_id', 'nama', 'warna']),
    ],
  },
  {
    path: '/katalog',
    label: 'Katalog risiko',
    group: 'Referensi',
    icon: BookOpen,
    description: 'Referensi ancaman dan kerentanan untuk penilaian risiko.',
    resources: [
      resource('ancaman', 'Ancaman', ['kode', 'nama', 'deskripsi']),
      resource('kerentanan', 'Kerentanan', ['kode', 'nama', 'deskripsi']),
      resource('kategori-aset', 'Kategori aset', ['kode', 'nama']),
      resource('klasifikasi-informasi', 'Klasifikasi informasi', ['kode', 'nama']),
    ],
  },
  {
    path: '/standar',
    label: 'Standar & regulasi',
    group: 'Referensi',
    icon: ShieldCheck,
    description: 'Standar kontrol dan regulasi yang mendasari kepatuhan.',
    resources: [
      resource('standar-kontrol', 'Standar', ['nama', 'versi']),
      resource('kontrol', 'Kontrol standar', ['kode', 'nama', 'jenis', 'standar_id']),
      resource('regulasi', 'Regulasi', ['kode', 'nama']),
    ],
  },
  {
    path: '/periode',
    label: 'Periode penilaian',
    group: 'Referensi',
    icon: CalendarDays,
    description: 'Jadwal penilaian dan referensi rencana kerja.',
    resources: [
      resource('periode-penilaian', 'Periode', ['nama', 'tanggal_mulai', 'tanggal_selesai']),
      resource('rencana-kerja', 'Rencana kerja', ['kode', 'uraian_template']),
    ],
  },
  {
    path: '/unit',
    label: 'Unit organisasi',
    group: 'Organisasi',
    icon: Network,
    description: 'Struktur organisasi dalam lingkup kewenangan Anda.',
    resources: [
      resource('unit-organisasi', 'Unit', ['kode', 'nama', 'jenis', 'parent_id', 'aktif']),
    ],
  },
  {
    path: '/pegawai',
    label: 'Pegawai',
    group: 'Organisasi',
    icon: ContactRound,
    description: 'Identitas pegawai dan penempatan organisasi.',
    resources: [
      resource('pegawai', 'Pegawai', ['nip', 'nama', 'jenis_kepegawaian', 'aktif']),
      resource('penempatan-pegawai', 'Penempatan', [
        'pegawai_id',
        'unit_id',
        'utama',
        'mulai',
        'selesai',
      ]),
    ],
  },
  {
    path: '/pengguna',
    label: 'Pengguna & akses',
    group: 'Administrasi',
    icon: UsersRound,
    admin: true,
    description: 'Akun pengguna dan penugasan role dalam unit organisasi.',
    resources: [
      resource('users', 'Pengguna', ['username', 'email_login', 'aktif', 'wajib_ganti_password']),
      resource('user-role-scope', 'Role scope', [
        'user_id',
        'role_id',
        'unit_id',
        'mulai',
        'selesai',
      ]),
      resource('roles', 'Daftar role', ['kode', 'nama']),
    ],
  },
  {
    path: '/audit',
    label: 'Jejak audit',
    group: 'Administrasi',
    icon: History,
    admin: true,
    description: 'Jejak perubahan dan aktivitas yang dicatat oleh sistem.',
    resources: [
      resource('log-audit', 'Log audit', ['waktu', 'user_id', 'aksi', 'entitas', 'entitas_id']),
    ],
  },
]
export const attachmentIcon = Paperclip
export const labels: Record<string, string> = {
  id: 'ID',
  unit_id: 'ID unit',
  unit_pemilik_id: 'ID unit pemilik',
  periode_id: 'ID periode',
  nomor_versi: 'Versi',
  kode_aset: 'Kode aset',
  wajib_ganti_password: 'Wajib ganti sandi',
  email_login: 'Email',
  ukuran_byte: 'Ukuran (byte)',
  status_pemeriksaan: 'Pemeriksaan',
  dibekukan: 'Metode beku',
  status_penilaian: 'Status penilaian',
  perlu_ditinjau: 'Perlu ditinjau',
  pic_id: 'ID penanggung jawab',
}
export function label(field: string) {
  return labels[field] || field.replaceAll('_', ' ').replace(/^./, (c) => c.toUpperCase())
}
export function display(value: unknown): string {
  if (value == null || value === '') return '-'
  if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
