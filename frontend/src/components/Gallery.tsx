import { useState } from 'react'
import { toast } from 'sonner'
import { PageHeader } from './PageHeader'
import { DataTable } from './DataTable'
import { Drawer } from './Drawer'
import { ConfirmDialog } from './ConfirmDialog'
import { TreeView } from './TreeView'
import { FilterBar } from './FilterBar'
import { EmptyState, ErrorState, Skeleton } from './States'
import { StatusBadge } from './StatusBadge'
import { RiskLevelChip } from './RiskLevelChip'
import s from './Gallery.module.css'
import { MatriksGrid } from './MatriksGrid'
import { SegelBeku } from './SegelBeku'
import { DiffViewer } from './DiffViewer'
const sampleScale = [
  { nilai: 1, nama: 'Rendah', kriteria: 'Dampak terbatas.' },
  { nilai: 2, nama: 'Tinggi', kriteria: 'Gangguan layanan utama.' },
]
export function Gallery() {
  const [search, setSearch] = useState('')
  const [drawer, setDrawer] = useState(false)
  const [confirm, setConfirm] = useState(false)
  const [selected, setSelected] = useState(1)
  const [cells, setCells] = useState<string[]>(['1-1', '2-1'])
  const [readonly, setReadonly] = useState(false)
  return (
    <>
      <PageHeader
        title="Galeri komponen"
        description="Referensi interaksi, keadaan, dan aksesibilitas SIMRIS."
      />
      <section className={s.section}>
        <h2>Register data</h2>
        <FilterBar value={search} onChange={setSearch} />
        <DataTable
          caption="Contoh register"
          data={[
            { id: 1, nama: 'Sekretariat utama', status: 'aktif' },
            { id: 2, nama: 'Bidang tata kelola', status: 'nonaktif' },
          ].filter((r) => r.nama.toLowerCase().includes(search.toLowerCase()))}
          columns={[
            { key: 'nama', label: 'Nama unit' },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
          ]}
          onSelect={() => setDrawer(true)}
        />
      </section>
      <section className={s.section}>
        <h2>Lapisan dan konfirmasi</h2>
        <div className={s.row}>
          <button onClick={() => setDrawer(true)}>Buka rincian</button>
          <button onClick={() => setConfirm(true)}>Konfirmasi pembekuan</button>
          <button onClick={() => toast.success('Perubahan disimpan')}>Tampilkan notifikasi</button>
          <button disabled title="Data masih dipakai transaksi">
            Nonaktifkan (terkunci)
          </button>
        </div>
      </section>
      <section className={s.section}>
        <h2>Status dan tingkat risiko</h2>
        <div className={s.row}>
          {['aktif', 'draf', 'dibekukan', 'status baru'].map((status) => (
            <StatusBadge key={status} status={status} />
          ))}
          {['#2e8b7a', '#8fb339', '#e3b23c', '#e07b39', '#c23b3b'].map((color, i) => (
            <RiskLevelChip
              key={color}
              color={color}
              name={['Sangat rendah', 'Rendah', 'Sedang', 'Tinggi', 'Sangat tinggi'][i]}
            />
          ))}
        </div>
      </section>
      <section className={s.section}>
        <h2>Pohon unit</h2>
        <TreeView
          nodes={[
            {
              id: 1,
              label: 'Sekretariat utama',
              children: [{ id: 2, label: 'Bidang tata kelola' }],
            },
          ]}
          selected={selected}
          onSelect={setSelected}
        />
      </section>
      <section className={s.section}>
        <h2>Keadaan kosong, galat, dan memuat</h2>
        <EmptyState
          title="Belum ada penempatan"
          description="Tambahkan penempatan agar pegawai ini muncul di struktur unit."
          action={<button onClick={() => setDrawer(true)}>Tambah penempatan</button>}
        />
        <ErrorState
          message="Koneksi terputus. Periksa jaringan, lalu coba lagi."
          onRetry={() => toast('Memuat ulang data')}
        />
        <Skeleton />
      </section>
      <section className={s.section}>
        <h2>Matriks, seleksi jamak, dan segel</h2>
        <label>
          <input
            type="checkbox"
            checked={readonly}
            onChange={(event) => setReadonly(event.target.checked)}
          />{' '}
          Hanya-lihat
        </label>
        <MatriksGrid
          kemungkinan={sampleScale}
          dampak={sampleScale}
          tingkat={[{ id: 1, nama: 'Rendah', warna: '#8fb339' }]}
          cells={[{ kemungkinan: 1, dampak: 1, skor: 1, tingkat_id: 1 }]}
          selected={cells}
          onSelect={(key) =>
            setCells((previous) =>
              previous.includes(key) ? previous.filter((item) => item !== key) : [...previous, key],
            )
          }
          readOnly={readonly}
        />
        <SegelBeku date="2026-10-12T03:00:00Z" />
      </section>
      <section className={s.section}>
        <h2>Perubahan data</h2>
        <DiffViewer
          before={{ nama: 'Unit lama', kode: 'U01', catatan: 'Dihapus' }}
          after={{ nama: 'Unit baru', kode: 'U01', pimpinan: 'Ratna Sari' }}
        />
      </section>
      <Drawer open={drawer} onClose={() => setDrawer(false)} title="Rincian unit">
        <p>Fokus tetap di dalam drawer. Tekan Esc untuk kembali ke register.</p>
        <label htmlFor="gallery-name">Nama unit</label>
        <input id="gallery-name" defaultValue="Sekretariat utama" />
        <label htmlFor="gallery-invalid">Kode unit</label>
        <input id="gallery-invalid" aria-invalid="true" aria-describedby="gallery-error" />
        <p id="gallery-error" className="field-error" role="alert">
          Kode unit wajib diisi. Masukkan kode unik unit.
        </p>
      </Drawer>
      <ConfirmDialog
        open={confirm}
        onClose={() => setConfirm(false)}
        title="Bekukan versi"
        description={[
          'Versi menjadi hanya-lihat.',
          'Perubahan berikutnya memerlukan duplikasi versi.',
        ]}
        confirmation="Metode 2026"
        confirmLabel="Bekukan versi"
        onConfirm={() => {
          setConfirm(false)
          toast.success('Versi dibekukan')
        }}
      />
    </>
  )
}
