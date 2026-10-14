import { http, HttpResponse } from 'msw'
import type { Asesmen } from '../types'
import { authorized, readDb } from './data'
const records: Asesmen[] = [
  'Layanan pusat data',
  'Sistem persuratan elektronik',
  'Pengelolaan kepegawaian',
  'Layanan jaringan internal',
  'Pengadaan barang dan jasa',
  'Pengelolaan arsip',
  'Portal layanan publik',
  'Pengelolaan anggaran',
  'Sistem informasi aset',
  'Layanan perencanaan',
  'Administrasi umum',
  'Sistem pelaporan',
].map((nama, i) => ({
  id: i + 1,
  kode: `ASM-2026-${String(i + 1).padStart(3, '0')}`,
  nama,
  unit: ['Bidang teknologi informasi', 'Sekretariat utama', 'Bidang tata kelola'][i % 3],
  status: ['selesai', 'dalam_penilaian', 'ditinjau', 'selesai', 'draf', 'dalam_penilaian'][i % 6],
  periode: 'Semester II 2026',
  metode: 'Metode risiko 2026',
  diperbarui: '2026-10-12T02:30:00Z',
  jenis: 'asesmen',
  lampiran_status: 'tersinkron',
  riwayat: [
    {
      versi: 1,
      tahap: 'Identifikasi risiko',
      tanggal: '2026-08-01T02:00:00Z',
      pelaku: 'Siti Rahmawati',
    },
    {
      versi: 2,
      tahap: 'Analisis dan evaluasi',
      tanggal: '2026-09-10T03:00:00Z',
      pelaku: 'Budi Santoso',
    },
    {
      versi: 3,
      tahap: 'Rencana perlakuan risiko',
      tanggal: '2026-10-12T02:30:00Z',
      pelaku: 'Ratna Wulandari',
    },
  ],
}))
for (const [index, jenis] of ['soa', 'sop', 'dokumen', 'lampiran'].entries()) {
  for (let i = 0; i < 3; i++)
    records.push({
      ...records[i],
      id: 100 + index * 10 + i,
      kode: `${jenis.toUpperCase()}-2026-${String(i + 1).padStart(3, '0')}`,
      nama: `${{ soa: 'Pernyataan penerapan kontrol', sop: 'Prosedur pengamanan layanan', dokumen: 'Dokumen penilaian risiko', lampiran: 'Bukti pengujian kontrol' }[jenis]} — ${records[i].nama}`,
      jenis,
      lampiran_status: jenis === 'lampiran' && i === 0 ? 'bermasalah' : 'tersinkron',
    })
}
export const monitoringHandlers = [
  http.get('/api/monitoring', () => authorized() || HttpResponse.json({ data: records })),
  http.get('/api/audit', () => authorized() || HttpResponse.json({ data: readDb().audit })),
  http.post('/api/monitoring/*', () =>
    HttpResponse.json(
      { message: 'Hanya lihat. Super admin tidak mengubah data transaksi.' },
      { status: 403 },
    ),
  ),
]
