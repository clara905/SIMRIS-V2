import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useAudit } from '../../api/audit'
import { useSession } from '../../api/auth'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { FilterBar } from '../../components/FilterBar'
import { DataTable } from '../../components/DataTable'
import { Drawer } from '../../components/Drawer'
import { DiffViewer } from '../../components/DiffViewer'
import { ErrorState, Skeleton } from '../../components/States'
import { tanggal, waktu } from '../../lib/format'
import s from '../Records.module.css'
export default function AuditPage() {
  const query = useAudit()
  const session = useSession()
  const [params] = useSearchParams()
  const [search, setSearch] = useState(params.get('nama') || '')
  const [entity, setEntity] = useState(params.get('entitas') || '')
  const [action, setAction] = useState('')
  const [date, setDate] = useState('')
  const [selected, setSelected] = useState<number | null>(Number(params.get('id')) || null)
  const record = query.data?.find((a) => a.id === selected)
  const data = (query.data || []).filter(
    (a) =>
      `${a.nama} ${a.pelaku}`.toLowerCase().includes(search.toLowerCase()) &&
      (!entity || a.entitas.toLowerCase() === entity.toLowerCase()) &&
      (!action || a.aksi === action) &&
      (!date ||
        new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date(a.waktu)) ===
          date),
  )
  return (
    <>
      <PageHeader
        title="Log audit"
        description="Telusuri siapa mengubah apa, kapan, dan bagaimana data berubah."
      />
      <FilterBar value={search} onChange={setSearch} placeholder="Cari pelaku atau nama data…">
        <select
          aria-label="Filter entitas"
          value={entity}
          onChange={(e) => setEntity(e.target.value)}
        >
          <option value="">Semua entitas</option>
          {[...new Set(query.data?.map((a) => a.entitas))].map((e) => (
            <option key={e} value={e}>
              {e}
            </option>
          ))}
        </select>
        <select aria-label="Filter aksi" value={action} onChange={(e) => setAction(e.target.value)}>
          <option value="">Semua aksi</option>
          {[...new Set(query.data?.map((a) => a.aksi))].map((a) => (
            <option key={a}>{a}</option>
          ))}
        </select>
        <label>
          Tanggal kejadian
          <input
            type="date"
            aria-label="Tanggal kejadian"
            value={date}
            onChange={(e) => setDate(e.target.value)}
          />
        </label>
      </FilterBar>
      {query.isPending ? (
        <Skeleton />
      ) : query.isError ? (
        <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
      ) : (
        <DataTable
          userId={session.data?.id}
          caption="Jejak audit"
          data={data}
          onSelect={(a) => setSelected(a.id)}
          columns={[
            { key: 'nama', label: 'Data' },
            {
              key: 'waktu',
              label: 'Waktu',
              render: (a) => `${tanggal(a.waktu)}, ${waktu(a.waktu)}`,
            },
            { key: 'pelaku', label: 'Pelaku' },
            { key: 'aksi', label: 'Aksi' },
            { key: 'entitas', label: 'Entitas' },
          ]}
        />
      )}
      <Drawer
        open={!!record}
        onClose={() => setSelected(null)}
        title="Rincian jejak audit"
        description="Catatan perubahan hanya-lihat."
      >
        {record && (
          <>
            <dl className={s.meta}>
              <dt>Data</dt>
              <dd>{record.nama}</dd>
              <dt>Pelaku</dt>
              <dd>{record.pelaku}</dd>
              <dt>Waktu</dt>
              <dd>
                {tanggal(record.waktu)}, {waktu(record.waktu)}
              </dd>
              <dt>Aksi</dt>
              <dd>{record.aksi}</dd>
              <dt>Entitas</dt>
              <dd>{record.entitas}</dd>
            </dl>
            <h2>Perubahan data</h2>
            <DiffViewer before={record.data_lama} after={record.data_baru} />
          </>
        )}
      </Drawer>
    </>
  )
}
