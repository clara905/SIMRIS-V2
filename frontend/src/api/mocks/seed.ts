import type { Pengguna, Unit, Pegawai, Audit } from '../types'
export const units: Unit[] = [
  {
    id: 1,
    kode: 'SET',
    nama: 'Sekretariat utama',
    jenis: 'Satker',
    induk_id: null,
    status: 'aktif',
    pimpinan: 'Dr. Ratna Wulandari',
    pegawai_aktif: 0,
  },
  {
    id: 2,
    kode: 'TK',
    nama: 'Bidang tata kelola',
    jenis: 'Bidang',
    induk_id: 1,
    status: 'aktif',
    pimpinan: 'Budi Santoso',
    pegawai_aktif: 0,
  },
  {
    id: 3,
    kode: 'TI',
    nama: 'Bidang teknologi informasi',
    jenis: 'Bidang',
    induk_id: 1,
    status: 'aktif',
    pimpinan: '',
    pegawai_aktif: 0,
  },
  {
    id: 4,
    kode: 'INF',
    nama: 'Subbidang infrastruktur',
    jenis: 'Subbidang',
    induk_id: 3,
    status: 'aktif',
    pimpinan: 'Dimas Saputra',
    pegawai_aktif: 0,
  },
  {
    id: 5,
    kode: 'REN',
    nama: 'Bidang perencanaan',
    jenis: 'Bidang',
    induk_id: 1,
    status: 'aktif',
    pimpinan: '',
    pegawai_aktif: 0,
  },
]
const names = [
  'Aditya Pratama',
  'Ratna Wulandari',
  'Budi Santoso',
  'Siti Rahmawati',
  'Dimas Saputra',
  'Nadia Putri',
  'Fajar Nugroho',
  'Dewi Lestari',
  'Rizky Hidayat',
  'Arif Wibowo',
  'Rina Kusuma',
  'Agus Setiawan',
]
export const users: Pengguna[] = names.map((nama, i) => ({
  id: i + 1,
  nama,
  email: i === 0 ? 'admin@simris.go.id' : nama.toLowerCase().replace(' ', '.') + '@instansi.go.id',
  status: i === 10 ? 'nonaktif' : 'aktif',
  terakhir_masuk: '2026-10-12T02:15:00Z',
  roles:
    i > 8
      ? []
      : [
          {
            id: i + 1,
            role: ['Super admin', 'Kasatker', 'Kabid', 'Pemilik risiko', 'Operator'][i % 5],
            unit_id: (i % 4) + 1,
            unit_nama: units[i % 4].nama,
            mulai: '2026-01-01',
            berakhir: null,
          },
        ],
}))
users[5].roles[0].role = 'Operator'
export const employees: Pegawai[] = names.slice(1).map((nama, i) => ({
  id: i + 1,
  nama,
  nip: `19850112201001${String(i + 1).padStart(4, '0')}`,
  email: nama.toLowerCase().replace(' ', '.') + '@instansi.go.id',
  status: 'aktif',
  penempatan:
    i > 6
      ? []
      : [
          {
            id: i + 1,
            unit_id: (i % 4) + 1,
            unit_nama: units[i % 4].nama,
            jabatan: ['Analis risiko', 'Kepala bidang', 'Pranata komputer'][i % 3],
            mulai: '2026-01-01',
            berakhir: null,
          },
        ],
}))
export const audits: Audit[] = [
  {
    id: 1,
    waktu: '2026-10-12T03:24:00Z',
    pelaku: 'Aditya Pratama',
    aksi: 'Memperbarui',
    entitas: 'Pengguna',
    nama: 'Siti Rahmawati',
    data_lama: { status: 'nonaktif' },
    data_baru: { status: 'aktif' },
  },
  {
    id: 2,
    waktu: '2026-10-12T02:45:00Z',
    pelaku: 'Aditya Pratama',
    aksi: 'Membekukan',
    entitas: 'Metode risiko',
    nama: 'Metode risiko 2026',
    data_lama: { status: 'draf' },
    data_baru: { status: 'dibekukan' },
  },
  {
    id: 3,
    waktu: '2026-10-12T01:30:00Z',
    pelaku: 'Aditya Pratama',
    aksi: 'Menambahkan',
    entitas: 'Unit organisasi',
    nama: 'Bidang perencanaan',
    data_lama: {},
    data_baru: { nama: 'Bidang perencanaan', kode: 'REN' },
  },
]
