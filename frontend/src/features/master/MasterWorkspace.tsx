import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Plus, Upload, Download, RefreshCw, Info } from 'lucide-react'
import { toast } from 'sonner'
import { useMasterAction, useSaveMaster } from '../../api/master'
import type { useMaster } from '../../api/master'
import type { Master } from '../../api/types'
import { useSession } from '../../api/auth'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { DataTable } from '../../components/DataTable'
import { FilterBar } from '../../components/FilterBar'
import { Drawer } from '../../components/Drawer'
import { RecordForm } from '../../components/RecordForm'
import type { FormField } from '../../components/RecordForm'
import { TreeView } from '../../components/TreeView'
import type { TreeNode } from '../../components/TreeView'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { TabBar } from '../../components/TabBar'
import { StatusBadge } from '../../components/StatusBadge'
import { ErrorState, Skeleton } from '../../components/States'
import { tanggal, waktu } from '../../lib/format'
import { downloadCsv } from '../../lib/csv'
import { ImportCsv } from './ImportCsv'
import s from '../Records.module.css'
export interface MasterTab {
  value: string
  label: string
  singular: string
  tree?: boolean
  dates?: boolean
}
export function MasterWorkspace({
  title,
  description,
  tabs,
  query,
  csv = false,
}: {
  title: string
  description: string
  tabs: MasterTab[]
  query: ReturnType<typeof useMaster>
  csv?: boolean
}) {
  const [params, setParams] = useSearchParams()
  const tab = tabs.find((t) => t.value === params.get('tab')) || tabs[0]
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [selected, setSelected] = useState<number | null>(null)
  const [form, setForm] = useState<'new' | 'edit' | null>(null)
  const [importing, setImporting] = useState(false)
  const [exporting, setExporting] = useState(false)
  const [confirm, setConfirm] = useState(false)
  const [standard, setStandard] = useState('')
  const save = useSaveMaster()
  const action = useMasterAction()
  const session = useSession()
  const all = query.data || []
  const record = all.find((m) => m.id === selected)
  const [now] = useState(() => Date.now())
  const filtered = all.filter(
    (m) =>
      m.jenis === tab.value &&
      `${m.nama} ${m.kode}`.toLowerCase().includes(search.toLowerCase()) &&
      (!status || m.status === status) &&
      (params.get('filter') !== 'berakhir' ||
        (!!m.selesai &&
          new Date(m.selesai).getTime() >= now &&
          new Date(m.selesai).getTime() - now <= 30 * 86400000)),
  )
  const standardId = Number(standard) || all.find((m) => m.jenis === 'standar')?.id || null
  const treeData = (parent: number | null, seen = new Set<number>()): TreeNode[] =>
    all
      .filter((m) => m.jenis === tab.value && m.induk_id === parent && !seen.has(m.id))
      .map((m) => ({
        id: m.id,
        label: `${m.kode} · ${m.nama}`,
        children: treeData(m.id, new Set([...seen, m.id])),
      }))
  const parents = all.filter(
    (m) =>
      m.id !== selected &&
      (tab.value === 'kontrol' ? ['standar', 'kontrol'].includes(m.jenis) : m.jenis === tab.value),
  )
  const fields: FormField[] = [
    { name: 'kode', label: 'Kode' },
    { name: 'nama', label: `Nama ${tab.singular}` },
    { name: 'deskripsi', label: 'Deskripsi', type: 'textarea', required: false },
    ...(tab.tree
      ? [
          {
            name: 'induk_id',
            label: tab.value === 'kontrol' ? 'Standar atau kontrol induk' : 'Kategori induk',
            type: 'select' as const,
            required: tab.value === 'kontrol',
            options: parents.map((p) => ({ value: String(p.id), label: `${p.kode} · ${p.nama}` })),
          },
        ]
      : []),
    ...(tab.dates
      ? [
          { name: 'mulai', label: 'Tanggal mulai', type: 'date' as const },
          { name: 'selesai', label: 'Tanggal selesai', type: 'date' as const },
        ]
      : []),
    ...(tab.value === 'sumber' ? [{ name: 'sumber', label: 'Identitas sumber' }] : []),
  ]
  const childCount = record
    ? all.filter((m) => m.induk_id === record.id && m.status === 'aktif').length
    : 0
  const detail = record && (
    <>
      <div className={s.panelHead}>
        <h2>{record.nama}</h2>
        <StatusBadge status={record.status} />
      </div>
      <dl className={s.meta}>
        <dt>Kode</dt>
        <dd>{record.kode}</dd>
        <dt>Deskripsi</dt>
        <dd>{record.deskripsi || 'Belum ada deskripsi.'}</dd>
        {record.induk_id && (
          <>
            <dt>Induk</dt>
            <dd>{all.find((m) => m.id === record.induk_id)?.nama || 'Tidak tersedia'}</dd>
          </>
        )}
        {tab.dates && (
          <>
            <dt>Tanggal mulai</dt>
            <dd>{tanggal(record.mulai)}</dd>
            <dt>Tanggal selesai</dt>
            <dd>{tanggal(record.selesai)}</dd>
          </>
        )}
        {tab.value === 'sumber' && (
          <>
            <dt>Sumber</dt>
            <dd>{record.sumber}</dd>
            <dt>Sinkronisasi</dt>
            <dd>
              {record.sinkron_pada
                ? `${tanggal(record.sinkron_pada)}, ${waktu(record.sinkron_pada)}`
                : 'Belum disinkronkan'}
            </dd>
          </>
        )}
      </dl>
      <div className={s.actions}>
        <button onClick={() => setForm('edit')}>Ubah {tab.singular}</button>
        <button
          disabled={childCount > 0 && record.status === 'aktif'}
          onClick={() => setConfirm(true)}
        >
          {record.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'}
        </button>
        {tab.value === 'sumber' && (
          <button
            disabled={action.isPending}
            onClick={async () => {
              try {
                const result = await action.mutateAsync({ id: record.id, action: 'sinkron' })
                toast.success(result.message)
              } catch (err) {
                toast.error(errorMessage(err))
              }
            }}
          >
            <RefreshCw size={16} />
            {action.isPending ? 'Menyinkronkan…' : 'Perbarui sinkronisasi'}
          </button>
        )}
      </div>
      {record.digunakan && (
        <div className={s.notice}>
          <Info size={16} />
          <p>
            Data sudah digunakan dalam transaksi dan tidak dapat dihapus. Nonaktifkan untuk
            menghentikan pemakaian pada transaksi baru.
          </p>
        </div>
      )}
      {childCount > 0 && (
        <p className={s.muted}>
          {childCount} anak aktif masih memakai data ini. Pindahkan atau nonaktifkan anak terlebih
          dahulu.
        </p>
      )}
    </>
  )
  return (
    <>
      <PageHeader
        title={title}
        description={description}
        actions={
          <>
            {csv && (
              <>
                <button onClick={() => setImporting(true)}>
                  <Upload size={16} />
                  Impor CSV
                </button>
                <button onClick={() => setExporting(true)}>
                  <Download size={16} />
                  Ekspor CSV
                </button>
              </>
            )}
            <button
              className="primary"
              onClick={() => {
                setSelected(null)
                setForm('new')
              }}
            >
              <Plus size={16} />
              Tambah {tab.singular}
            </button>
          </>
        }
      />
      <TabBar
        items={tabs}
        value={tab.value}
        onChange={(value) => {
          setParams({ tab: value })
          setSelected(null)
          setSearch('')
          setStatus('')
          setForm(null)
        }}
        label={`Bagian ${title.toLowerCase()}`}
      >
        <FilterBar value={search} onChange={setSearch}>
          <select
            aria-label="Filter status"
            value={status}
            onChange={(e) => setStatus(e.target.value)}
          >
            <option value="">Semua status</option>
            <option value="aktif">Aktif</option>
            <option value="nonaktif">Nonaktif</option>
          </select>
        </FilterBar>
        {query.isPending ? (
          <Skeleton />
        ) : query.isError ? (
          <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : tab.tree && !search && !status ? (
          <div className={s.split}>
            <section className={s.panel}>
              <h2>{tab.label}</h2>
              {tab.value === 'kontrol' && (
                <>
                  <label htmlFor="standard-select">Standar acuan</label>
                  <select
                    id="standard-select"
                    value={String(standardId || '')}
                    onChange={(e) => {
                      setStandard(e.target.value)
                      setSelected(null)
                    }}
                  >
                    {all
                      .filter((m) => m.jenis === 'standar')
                      .map((m) => (
                        <option value={m.id} key={m.id}>
                          {m.nama}
                        </option>
                      ))}
                  </select>
                </>
              )}
              <TreeView
                nodes={treeData(tab.value === 'kontrol' ? standardId : null)}
                selected={selected ?? undefined}
                onSelect={setSelected}
                label={`Pohon ${tab.singular}`}
              />
              {!treeData(tab.value === 'kontrol' ? standardId : null).length && (
                <p>Belum ada {tab.singular}. Tambahkan data untuk melengkapi register.</p>
              )}
            </section>
            <section className={s.panel}>
              {record ? (
                detail
              ) : (
                <>
                  <h2>Rincian {tab.singular}</h2>
                  <p className={s.muted}>
                    Pilih data pada pohon untuk melihat rincian dan mengelolanya.
                  </p>
                </>
              )}
            </section>
          </div>
        ) : (
          <DataTable
            userId={session.data?.id}
            caption={`Daftar ${tab.label.toLowerCase()}`}
            data={filtered}
            columns={[
              { key: 'nama', label: 'Nama' },
              { key: 'kode', label: 'Kode' },
              { key: 'status', label: 'Status', render: (m) => <StatusBadge status={m.status} /> },
              ...(tab.dates
                ? [
                    {
                      key: 'mulai' as const,
                      label: 'Mulai',
                      render: (m: Master) => tanggal(m.mulai),
                    },
                    {
                      key: 'selesai' as const,
                      label: 'Selesai',
                      render: (m: Master) => tanggal(m.selesai),
                    },
                  ]
                : [
                    {
                      key: 'digunakan' as const,
                      label: 'Pemakaian',
                      render: (m: Master) =>
                        m.digunakan ? 'Digunakan transaksi' : 'Belum digunakan',
                    },
                  ]),
              ...(tab.value === 'sumber'
                ? [
                    {
                      key: 'sinkron_pada' as const,
                      label: 'Sinkronisasi terakhir',
                      render: (m: Master) =>
                        m.sinkron_pada
                          ? `${tanggal(m.sinkron_pada)} · ${waktu(m.sinkron_pada)}`
                          : 'Belum disinkronkan',
                    },
                  ]
                : []),
            ]}
            onSelect={(m) => {
              setSelected(m.id)
              setForm(null)
            }}
          />
        )}
      </TabBar>
      <Drawer
        open={!!form || (!!record && !tab.tree)}
        onClose={() => {
          setForm(null)
          if (!tab.tree) setSelected(null)
        }}
        title={
          form === 'new'
            ? `Tambah ${tab.singular}`
            : form === 'edit'
              ? `Ubah ${tab.singular}`
              : record?.nama || ''
        }
      >
        {form ? (
          <RecordForm
            key={`${form}-${selected}-${tab.value}`}
            fields={fields}
            values={
              form === 'edit' && record
                ? {
                    kode: record.kode,
                    nama: record.nama,
                    deskripsi: record.deskripsi,
                    induk_id: String(record.induk_id || ''),
                    mulai: record.mulai,
                    selesai: record.selesai,
                    sumber: record.sumber,
                  }
                : { induk_id: tab.value === 'kontrol' ? String(standardId || '') : '' }
            }
            onCancel={() => setForm(null)}
            submitLabel={form === 'new' ? `Tambah ${tab.singular}` : 'Simpan perubahan'}
            onSave={async (v) => {
              const saved = await save.mutateAsync({
                ...(form === 'edit' ? record : {}),
                ...v,
                jenis: tab.value,
                induk_id: v.induk_id ? Number(v.induk_id) : null,
              })
              setSelected(saved.id)
              setForm(null)
              toast.success('Data disimpan')
            }}
          />
        ) : (
          detail
        )}
      </Drawer>
      <ConfirmDialog
        open={confirm}
        onClose={() => setConfirm(false)}
        title={
          record?.status === 'aktif' ? `Nonaktifkan ${tab.singular}` : `Aktifkan ${tab.singular}`
        }
        confirmLabel="Simpan status"
        description={[
          'Perubahan status memengaruhi pilihan pada transaksi berikutnya.',
          'Data dan riwayat yang sudah digunakan tetap tersimpan.',
        ]}
        pending={save.isPending}
        onConfirm={async () => {
          if (!record) return
          try {
            await save.mutateAsync({
              ...record,
              status: record.status === 'aktif' ? 'nonaktif' : 'aktif',
            })
            setConfirm(false)
            toast.success('Status diperbarui')
          } catch (err) {
            toast.error(errorMessage(err))
          }
        }}
      />
      {importing && (
        <ImportCsv
          open
          onClose={() => setImporting(false)}
          jenis={tab.value}
          existing={all}
          parentId={tab.value === 'kontrol' ? standardId : null}
        />
      )}
      <Drawer
        open={exporting}
        onClose={() => setExporting(false)}
        title="Pratinjau ekspor CSV"
        description="Data mengikuti tab dan filter yang sedang digunakan."
      >
        <p>{filtered.length} data akan diekspor dengan kolom kode, nama, deskripsi.</p>
        <DataTable
          caption="Pratinjau ekspor"
          data={filtered}
          columns={[
            { key: 'kode', label: 'Kode' },
            { key: 'nama', label: 'Nama' },
          ]}
        />
        <div className={s.actions}>
          <button
            className="primary"
            onClick={() => {
              downloadCsv(`simris-${tab.value}.csv`, [
                ['kode', 'nama', 'deskripsi'],
                ...filtered.map((m) => [m.kode, m.nama, m.deskripsi]),
              ])
              toast.success('CSV diekspor')
              setExporting(false)
            }}
          >
            <Download size={16} />
            Unduh CSV
          </button>
        </div>
      </Drawer>
    </>
  )
}
