import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { useMetode, useSaveMetode } from '../../api/metode'
import { useSession } from '../../api/auth'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { FilterBar } from '../../components/FilterBar'
import { DataTable } from '../../components/DataTable'
import { StatusBadge } from '../../components/StatusBadge'
import { Drawer } from '../../components/Drawer'
import { RecordForm } from '../../components/RecordForm'
import { ErrorState, Skeleton } from '../../components/States'
import { tanggal } from '../../lib/format'
import s from '../Records.module.css'
export default function MetodePage() {
  const query = useMetode()
  const save = useSaveMetode()
  const session = useSession()
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [create, setCreate] = useState(false)
  return (
    <>
      <PageHeader
        title="Metode risiko"
        description="Kelola skala, kriteria, dan matriks dalam versi yang dapat ditelusuri."
        actions={
          <button className="primary" onClick={() => setCreate(true)}>
            <Plus size={17} />
            Buat versi metode
          </button>
        }
      />
      <div className={s.notice}>
        Versi yang dibekukan menjadi acuan tetap penilaian. Duplikasi versi untuk menyusun perubahan
        berikutnya.
      </div>
      <FilterBar value={search} onChange={setSearch} placeholder="Cari nama versi…" />
      {query.isPending ? (
        <Skeleton />
      ) : query.isError ? (
        <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
      ) : (
        <DataTable
          caption="Versi metode risiko"
          userId={session.data?.id}
          data={query.data.filter((m) => m.nama.toLowerCase().includes(search.toLowerCase()))}
          columns={[
            { key: 'nama', label: 'Nama versi' },
            { key: 'status', label: 'Status', render: (m) => <StatusBadge status={m.status} /> },
            {
              key: 'matriks',
              label: 'Kelengkapan matriks',
              render: (m) =>
                `${m.matriks.filter((c) => c.tingkat_id).length} / ${m.kemungkinan.length * m.dampak.length} sel`,
              numeric: true,
            },
            {
              key: 'dibekukan_pada',
              label: 'Dibekukan pada',
              render: (m) => (m.dibekukan_pada ? tanggal(m.dibekukan_pada) : 'Belum dibekukan'),
            },
          ]}
          onSelect={(m) => navigate(`/metode-risiko/${m.id}`)}
        />
      )}
      <Drawer open={create} onClose={() => setCreate(false)} title="Buat versi metode">
        <RecordForm
          fields={[
            { name: 'nama', label: 'Nama versi' },
            { name: 'deskripsi', label: 'Deskripsi', type: 'textarea' },
          ]}
          submitLabel="Buat versi metode"
          onSave={async (values) => {
            const method = await save.mutateAsync(values)
            toast.success('Versi metode dibuat')
            navigate(`/metode-risiko/${method.id}`)
          }}
        />
      </Drawer>
    </>
  )
}
