import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { LockKeyhole } from 'lucide-react'
import { useMonitoring } from '../../api/monitoring'
import { useSession } from '../../api/auth'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { FilterBar } from '../../components/FilterBar'
import { DataTable } from '../../components/DataTable'
import { TabBar } from '../../components/TabBar'
import { Drawer } from '../../components/Drawer'
import { StatusBadge } from '../../components/StatusBadge'
import { ErrorState, Skeleton } from '../../components/States'
import { tanggal, waktu } from '../../lib/format'
import s from '../Records.module.css'
const tabs = [
  { value: 'asesmen', label: 'Asesmen' },
  { value: 'soa', label: 'SOA' },
  { value: 'sop', label: 'SOP' },
  { value: 'dokumen', label: 'Dokumen' },
  { value: 'lampiran', label: 'Lampiran' },
]
export function ReadOnlyBanner() {
  return (
    <div className={s.notice}>
      <LockKeyhole size={17} aria-hidden="true" />
      <p>Hanya lihat. Super admin tidak mengubah data transaksi.</p>
    </div>
  )
}
export default function MonitoringPage() {
  const query = useMonitoring()
  const session = useSession()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const tab = tabs.some((t) => t.value === params.get('tab')) ? params.get('tab')! : 'asesmen'
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState(params.get('status') || '')
  const [unit, setUnit] = useState('')
  const [selected, setSelected] = useState<number | null>(null)
  const record = query.data?.find((m) => m.id === selected)
  const data = (query.data || []).filter(
    (m) =>
      m.jenis === tab &&
      `${m.kode} ${m.nama}`.toLowerCase().includes(search.toLowerCase()) &&
      (!status || m.status === status) &&
      (!unit || m.unit === unit) &&
      (params.get('filter') !== 'bermasalah' || m.lampiran_status === 'bermasalah'),
  )
  return (
    <>
      <PageHeader
        title="Monitoring transaksi"
        description="Pantau proses penilaian, penerapan kontrol, dan dokumen pendukung."
      />
      <ReadOnlyBanner />
      <TabBar
        items={tabs}
        value={tab}
        onChange={(value) => {
          setParams({ tab: value })
          setSelected(null)
          setSearch('')
          setStatus('')
        }}
        label="Jenis transaksi"
      >
        <FilterBar value={search} onChange={setSearch}>
          <select
            aria-label="Filter status"
            value={status}
            onChange={(e) => setStatus(e.target.value)}
          >
            <option value="">Semua status</option>
            <option value="draf">Draf</option>
            <option value="dalam_penilaian">Dalam penilaian</option>
            <option value="ditinjau">Ditinjau</option>
            <option value="selesai">Selesai</option>
          </select>
          <select aria-label="Filter unit" value={unit} onChange={(e) => setUnit(e.target.value)}>
            <option value="">Semua unit</option>
            {[...new Set(query.data?.map((m) => m.unit))].map((u) => (
              <option key={u}>{u}</option>
            ))}
          </select>
        </FilterBar>
        {query.isPending ? (
          <Skeleton />
        ) : query.isError ? (
          <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : (
          <DataTable
            userId={session.data?.id}
            caption={`Daftar ${tab}`}
            data={data}
            onSelect={(m) =>
              tab === 'asesmen' ? navigate(`/monitoring/asesmen/${m.id}`) : setSelected(m.id)
            }
            columns={[
              { key: 'nama', label: 'Nama transaksi' },
              { key: 'kode', label: 'Kode' },
              { key: 'unit', label: 'Unit organisasi' },
              { key: 'status', label: 'Status', render: (m) => <StatusBadge status={m.status} /> },
              { key: 'diperbarui', label: 'Diperbarui', render: (m) => tanggal(m.diperbarui) },
              ...(tab === 'lampiran'
                ? [
                    {
                      key: 'lampiran_status' as const,
                      label: 'Pemeriksaan berkas',
                      render: (m: NonNullable<typeof record>) => (
                        <StatusBadge status={m.lampiran_status} />
                      ),
                    },
                  ]
                : []),
            ]}
          />
        )}
      </TabBar>
      <Drawer
        open={!!record}
        onClose={() => setSelected(null)}
        title={record?.nama || ''}
        description="Rincian transaksi hanya-lihat."
      >
        {record && (
          <>
            <ReadOnlyBanner />
            <StatusBadge status={record.status} />
            <dl className={s.meta}>
              <dt>Kode</dt>
              <dd>{record.kode}</dd>
              <dt>Unit organisasi</dt>
              <dd>{record.unit}</dd>
              <dt>Periode</dt>
              <dd>{record.periode}</dd>
              <dt>Metode</dt>
              <dd>{record.metode}</dd>
              <dt>Diperbarui</dt>
              <dd>
                {tanggal(record.diperbarui)}, {waktu(record.diperbarui)}
              </dd>
              {record.jenis === 'lampiran' && (
                <>
                  <dt>Pemeriksaan</dt>
                  <dd>
                    <StatusBadge status={record.lampiran_status} />
                  </dd>
                </>
              )}
            </dl>
            {record.lampiran_status === 'bermasalah' && (
              <div className={s.notice}>
                Integritas berkas belum dapat dipastikan. Pemilik transaksi perlu memeriksa atau
                mengunggah ulang berkas melalui akses operasionalnya.
              </div>
            )}
            <h2>Riwayat versi</h2>
            <ol className={s.timeline}>
              {record.riwayat.map((v) => (
                <li key={v.versi}>
                  <strong>
                    Versi {v.versi} · {v.tahap}
                  </strong>
                  <small>
                    {v.pelaku} · {tanggal(v.tanggal)}
                  </small>
                </li>
              ))}
            </ol>
          </>
        )}
      </Drawer>
    </>
  )
}
