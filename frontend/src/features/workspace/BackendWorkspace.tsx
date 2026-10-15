import { useState } from 'react'
import { Link, NavLink, useLocation, useNavigate, useSearchParams } from 'react-router-dom'
import { useQueries, useQuery, useQueryClient } from '@tanstack/react-query'
import { Dialog } from 'radix-ui'
import {
  ShieldCheck,
  ArrowUpRight,
  ArrowRight,
  Search,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  LogOut,
  Menu,
  X,
  Download,
  LockKeyhole,
  Activity,
  CircleCheck,
  Layers,
  FileCheck2,
} from 'lucide-react'
import { toast } from 'sonner'
import { api, errorMessage } from '../../api/client'
import { logout, useSession } from '../../api/auth'
import { Drawer } from '../../components/Drawer'
import { modules, label, display, type Resource } from './resources'
import './workspace.css'

type Row = Record<string, unknown> & { id: number }
type Page = {
  data: Row[]
  meta: {
    current_page: number
    last_page: number
    total: number
    from: number | null
    to: number | null
  }
}
async function getPage(
  endpoint: string,
  params: Record<string, unknown> = {},
  signal?: AbortSignal,
): Promise<Page> {
  const response = await api.get<Page>(endpoint, { params: { per_page: 10, ...params }, signal })
  if (!Array.isArray(response.data.data) || !response.data.meta)
    throw new Error('Respons API tidak memuat struktur paginasi yang sesuai.')
  return response.data
}
function Status({ value }: { value: unknown }) {
  const status = display(value)
  const tone = ['DISAHKAN', 'AMAN', 'FINAL', 'SELESAI'].includes(status)
    ? 'success'
    : ['DITOLAK', 'TIDAK_DITEMUKAN'].includes(status)
      ? 'danger'
      : status === 'DRAFT'
        ? 'neutral'
        : 'warning'
  return (
    <span className={'ws-badge ' + tone}>
      <span />
      {status.replaceAll('_', ' ')}
    </span>
  )
}
function Failure({ error, retry }: { error: unknown; retry: () => void }) {
  return (
    <div className="ws-empty" role="alert">
      <strong>Data belum dapat dimuat</strong>
      <p>{errorMessage(error)}</p>
      <button onClick={retry}>Coba lagi</button>
    </div>
  )
}
function Loading() {
  return (
    <div className="ws-loading" role="status" aria-label="Memuat data">
      <div />
      <div />
      <div />
      <span>Memuat data...</span>
    </div>
  )
}
async function download(endpoint: string, filename: string) {
  try {
    const response = await api.get(endpoint, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (error) {
    toast.error(errorMessage(error))
  }
}
export function BackendWorkspace() {
  const session = useSession()
  const client = useQueryClient()
  const navigate = useNavigate()
  const location = useLocation()
  const [mobile, setMobile] = useState(false)
  const [leaving, setLeaving] = useState(false)
  const [navSearch, setNavSearch] = useState('')
  const allowed = modules.filter(
    (m) => !m.admin || session.data?.roleCodes?.includes('SUPER_ADMIN'),
  )
  const active = allowed.find((m) => m.path === location.pathname)
  const navigation = (
    <nav aria-label="Navigasi utama">
      {allowed
        .filter((m) => m.label.toLowerCase().includes(navSearch.toLowerCase()))
        .map((m, index, list) => (
          <div key={m.path}>
            {(!index || list[index - 1].group !== m.group) && (
              <p className="ws-nav-group">{m.group}</p>
            )}
            <NavLink to={m.path} end onClick={() => setMobile(false)}>
              <m.icon size={18} />
              <span>{m.label}</span>
              {m.path === location.pathname && <span className="ws-active-dot" />}
            </NavLink>
          </div>
        ))}
    </nav>
  )
  const brand = (
    <Link to="/" className="ws-brand">
      <span>
        <ShieldCheck size={25} />
      </span>
      <div>
        SIMRIS<small>GOVERNANCE WORKSPACE</small>
      </div>
    </Link>
  )
  return (
    <div className="ws">
      <a href="#workspace-content" className="ws-skip">
        Lewati ke konten
      </a>
      <aside className="ws-sidebar">
        {brand}
        <div className="ws-nav-search">
          <Search size={15} />
          <input
            aria-label="Cari menu"
            placeholder="Cari menu..."
            value={navSearch}
            onChange={(e) => setNavSearch(e.target.value)}
          />
        </div>
        {navigation}
        <div className="ws-sidebar-note">
          <LockKeyhole size={18} />
          <div>
            Akses sesuai kewenangan<small>Lingkup unit mengikuti role aktif</small>
          </div>
        </div>
      </aside>
      <div className="ws-body">
        <header className="ws-topbar">
          <div className="ws-breadcrumb">
            <Dialog.Root open={mobile} onOpenChange={setMobile}>
              <Dialog.Trigger className="ws-mobile-button" aria-label="Buka navigasi">
                <Menu size={20} />
              </Dialog.Trigger>
              <Dialog.Portal>
                <Dialog.Overlay className="ws-nav-overlay" />
                <Dialog.Content className="ws-mobile-nav">
                  <Dialog.Title>SIMRIS</Dialog.Title>
                  <Dialog.Description>Navigasi ruang kerja</Dialog.Description>
                  <Dialog.Close aria-label="Tutup navigasi">
                    <X size={18} />
                  </Dialog.Close>
                  {navigation}
                </Dialog.Content>
              </Dialog.Portal>
            </Dialog.Root>
            <span>Ruang kerja</span>
            <ChevronRight size={14} />
            <strong>{active?.label || 'Halaman'}</strong>
          </div>
          <div className="ws-account">
            <span className="ws-avatar">{session.data?.nama.slice(0, 2).toUpperCase()}</span>
            <div>
              {session.data?.nama}
              <small>{session.data?.role}</small>
            </div>
            <button
              aria-label="Keluar"
              disabled={leaving}
              onClick={async () => {
                setLeaving(true)
                try {
                  await logout()
                  client.clear()
                  navigate('/masuk', { replace: true })
                } catch (error) {
                  toast.error(errorMessage(error))
                } finally {
                  setLeaving(false)
                }
              }}
            >
              <LogOut size={17} />
            </button>
          </div>
        </header>
        <main id="workspace-content" className="ws-content" tabIndex={-1}>
          {!active ? (
            <section className="ws-panel ws-empty">
              <h1>Halaman tidak tersedia</h1>
              <p>Periksa alamat atau hak akses akun Anda.</p>
              <Link to="/">Kembali ke ringkasan</Link>
            </section>
          ) : active.path === '/' ? (
            <Overview />
          ) : (
            <>
              <div className="ws-page-heading">
                <div>
                  <span className="ws-eyebrow">{active.group}</span>
                  <h1>{active.label}</h1>
                  <p>{active.description}</p>
                </div>
                <span className="ws-read-label">
                  <LockKeyhole size={14} /> Tampilan pemantauan
                </span>
              </div>
              <Registry key={active.path} resources={active.resources} />
            </>
          )}
          <footer className="ws-footer">
            <span>SIMRIS / Sistem Informasi Manajemen Risiko</span>
            <span>Kementerian Pertahanan RI</span>
          </footer>
        </main>
      </div>
    </div>
  )
}
function Overview() {
  const session = useSession()
  const cards = [
    {
      label: 'Asesmen terdaftar',
      endpoint: '/api/monitoring/asesmen',
      icon: Layers,
      path: '/monitoring',
      description: 'Seluruh tahap penilaian',
    },
    {
      label: 'Asesmen disahkan',
      endpoint: '/api/monitoring/asesmen',
      filter: { status: 'DISAHKAN' },
      icon: CircleCheck,
      path: '/monitoring?status=DISAHKAN',
      description: 'Keputusan telah ditetapkan',
    },
    {
      label: 'Aset terinventaris',
      endpoint: '/api/aset',
      icon: ShieldCheck,
      path: '/aset',
      description: 'Dalam lingkup akses Anda',
    },
    {
      label: 'Dokumen pendukung',
      endpoint: '/api/monitoring/dokumen',
      icon: FileCheck2,
      path: '/dokumen',
      description: 'Bukti dan referensi organisasi',
    },
  ]
  const counts = useQueries({
    queries: cards.map((c) => ({
      queryKey: ['workspace', 'count', c.endpoint, c.filter],
      queryFn: ({ signal }: { signal: AbortSignal }) =>
        getPage(c.endpoint, { per_page: 1, filter: c.filter }, signal),
    })),
  })
  const latest = useQuery({
    queryKey: ['workspace', 'recent'],
    queryFn: ({ signal }) =>
      getPage('/api/monitoring/asesmen', { per_page: 5, sort: '-id' }, signal),
  })
  const statuses = [
    'DRAFT',
    'MENUNGGU_KASUB',
    'MENUNGGU_KABID',
    'PENANGANAN',
    'MENUNGGU_KASATKER',
    'DISAHKAN',
  ]
  const stages = useQueries({
    queries: statuses.map((status) => ({
      queryKey: ['workspace', 'stage', status],
      queryFn: ({ signal }: { signal: AbortSignal }) =>
        getPage('/api/monitoring/asesmen', { per_page: 1, filter: { status } }, signal),
    })),
  })
  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">PUSAT PEMANTAUAN RISIKO</span>
          <h1>Ringkasan ruang kerja</h1>
          <p>Selamat datang, {session.data?.nama}. Pantau risiko, jaga kesiapan organisasi.</p>
        </div>
        <span className="ws-date">
          {new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'Asia/Jakarta' }).format(
            new Date(),
          )}
        </span>
      </div>
      <section className="ws-hero">
        <div className="ws-hero-copy">
          <span className="ws-hero-kicker">
            <span /> TATA KELOLA TERINTEGRASI
          </span>
          <h2>
            Keputusan yang terukur.
            <br />
            Organisasi yang lebih siap.
          </h2>
          <p>Telusuri asesmen, bukti pendukung, dan penerapan kontrol dalam satu ruang kerja.</p>
          <Link className="ws-hero-button" to="/monitoring">
            Tinjau asesmen <ArrowUpRight size={18} />
          </Link>
        </div>
        <div className="ws-orbit" aria-hidden="true">
          <div className="ws-orbit-ring" />
          <div className="ws-orbit-ring inner" />
          <div className="ws-orbit-core">
            <ShieldCheck size={55} />
          </div>
          <span className="ws-orbit-label top">IDENTIFIKASI</span>
          <span className="ws-orbit-label bottom">MONITORING</span>
          <span className="ws-orbit-point" />
        </div>
      </section>
      <section className="ws-metrics" aria-label="Ringkasan data">
        {cards.map((card, i) => (
          <Link to={card.path} className="ws-metric" key={card.label}>
            <div>
              <span>{card.label}</span>
              <card.icon size={20} />
            </div>
            <strong>
              {counts[i].isPending
                ? '...'
                : counts[i].isError
                  ? 'Tidak tersedia'
                  : counts[i].data?.meta.total.toLocaleString('id-ID')}
            </strong>
            <small>
              {counts[i].isError ? 'Buka modul untuk mencoba lagi' : card.description}
              <ArrowUpRight size={14} />
            </small>
          </Link>
        ))}
      </section>
      <div className="ws-overview-grid">
        <section className="ws-panel">
          <header className="ws-panel-heading">
            <div>
              <h2>Asesmen terbaru</h2>
              <p>Urutan berdasarkan ID asesmen terbaru.</p>
            </div>
            <Link to="/monitoring">
              Lihat semua <ArrowRight size={15} />
            </Link>
          </header>
          {latest.isPending ? (
            <Loading />
          ) : latest.isError ? (
            <Failure error={latest.error} retry={() => void latest.refetch()} />
          ) : !latest.data.data.length ? (
            <div className="ws-empty">
              <Layers size={30} />
              <strong>Belum ada asesmen</strong>
              <p>Asesmen dalam lingkup Anda akan tampil di sini.</p>
            </div>
          ) : (
            <div className="ws-table-wrap">
              <table className="ws-table">
                <thead>
                  <tr>
                    <th>Asesmen / tujuan</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>
                      <span className="sr-only">Buka</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {latest.data.data.map((row) => (
                    <tr key={row.id}>
                      <td>
                        <Link to={'/monitoring?id=' + row.id}>
                          <strong>{display(row.tujuan)}</strong>
                        </Link>
                        <small>
                          ASESMEN #{row.id} / V{display(row.nomor_versi)}
                        </small>
                      </td>
                      <td>#{display(row.unit_id)}</td>
                      <td>
                        <Status value={row.status} />
                      </td>
                      <td>
                        <Link aria-label={'Buka asesmen ' + row.id} to={'/monitoring?id=' + row.id}>
                          <ArrowUpRight size={18} />
                        </Link>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
        <section className="ws-panel">
          <header className="ws-panel-heading">
            <div>
              <h2>Tahap asesmen</h2>
              <p>Jumlah pada tahap utama workflow.</p>
            </div>
            <Activity size={19} />
          </header>
          <div className="ws-stages">
            {statuses.map((status, i) => (
              <Link to={'/monitoring?status=' + status} key={status}>
                <span className="ws-stage-number">{String(i + 1).padStart(2, '0')}</span>
                <span>{label(status.toLowerCase())}</span>
                <strong>
                  {stages[i].isPending
                    ? '...'
                    : stages[i].isError
                      ? 'N/A'
                      : stages[i].data?.meta.total}
                </strong>
                <ChevronRight size={15} />
              </Link>
            ))}
          </div>
          <p className="ws-stage-note">
            Termasuk tahap awal dan keputusan akhir. Tahap residual serta penolakan tersedia di
            register asesmen.
          </p>
        </section>
      </div>
      <div className="ws-quick-links">
        {[
          {
            path: '/kepatuhan',
            label: 'Kontrol & kepatuhan',
            text: 'Telusuri SOP dan SOA',
            icon: ShieldCheck,
          },
          {
            path: '/dokumen',
            label: 'Dokumen & bukti',
            text: 'Periksa dokumen pendukung',
            icon: FileCheck2,
          },
          {
            path: '/metode-risiko',
            label: 'Metode penilaian',
            text: 'Lihat referensi metode risiko',
            icon: Layers,
          },
        ].map((item) => (
          <Link to={item.path} key={item.path}>
            <item.icon size={25} />
            <div>
              <strong>{item.label}</strong>
              <small>{item.text}</small>
            </div>
            <ArrowUpRight size={18} />
          </Link>
        ))}
      </div>
    </>
  )
}
function Registry({ resources }: { resources: Resource[] }) {
  const [params, setParams] = useSearchParams()
  const resource = resources.find((r) => r.key === params.get('tab')) || resources[0]
  const page = Math.max(1, Number(params.get('page')) || 1)
  const status = params.get('status') || ''
  const id = params.get('id') || ''
  const [idDraft, setIdDraft] = useState(id)
  const [selected, setSelected] = useState<Row | null>(null)
  const filter = {
    ...(id ? { id } : {}),
    ...(status && resource.statuses?.includes(status)
      ? { [resource.statusField || 'status']: status }
      : {}),
  }
  const query = useQuery({
    queryKey: ['workspace', 'list', resource.endpoint, page, filter],
    queryFn: ({ signal }) => getPage(resource.endpoint, { page, filter, sort: '-id' }, signal),
  })
  function update(values: Record<string, string>) {
    const next = new URLSearchParams(params)
    Object.entries(values).forEach(([key, value]) =>
      value ? next.set(key, value) : next.delete(key),
    )
    setParams(next)
  }
  return (
    <>
      <div className="ws-tabs" role="group" aria-label="Jenis data">
        {resources.map((r) => (
          <button
            key={r.key}
            aria-pressed={r.key === resource.key}
            onClick={() => {
              setParams({ tab: r.key })
              setIdDraft('')
              setSelected(null)
            }}
          >
            {r.label}
          </button>
        ))}
      </div>
      <section className="ws-panel">
        <div className="ws-register-tools">
          <form
            onSubmit={(e) => {
              e.preventDefault()
              update({ id: idDraft, page: '' })
            }}
            className="ws-filter"
          >
            <Search size={17} />
            <input
              aria-label="Cari berdasarkan ID"
              type="number"
              min="1"
              step="1"
              placeholder="Cari berdasarkan ID..."
              value={idDraft}
              onChange={(e) => setIdDraft(e.target.value)}
            />
            <button type="submit">Cari</button>
          </form>
          {resource.statuses && (
            <select
              aria-label="Filter status"
              value={status}
              onChange={(e) => update({ status: e.target.value, page: '' })}
            >
              <option value="">Semua status</option>
              {resource.statuses.map((value) => (
                <option key={value}>{value}</option>
              ))}
            </select>
          )}
          {(id || status) && (
            <button
              onClick={() => {
                setIdDraft('')
                setParams({ tab: resource.key })
              }}
            >
              Reset filter
            </button>
          )}
          <button
            className="ws-refresh"
            aria-label="Perbarui data"
            disabled={query.isFetching}
            onClick={() => void query.refetch()}
          >
            <RefreshCw size={16} />
          </button>
          {resource.key === 'log-audit' && (
            <button
              onClick={() =>
                void download(
                  '/api/log-audit/ekspor' + (id ? '?filter[id]=' + encodeURIComponent(id) : ''),
                  'log-audit.csv',
                )
              }
            >
              <Download size={16} />
              Ekspor CSV
            </button>
          )}
        </div>
        {query.isPending ? (
          <Loading />
        ) : query.isError ? (
          <Failure error={query.error} retry={() => void query.refetch()} />
        ) : (
          <>
            {!query.data.data.length ? (
              <div className="ws-empty">
                <Search size={30} />
                <strong>Tidak ada data yang ditemukan</strong>
                <p>Coba ubah filter atau periksa kembali lingkup unit Anda.</p>
              </div>
            ) : (
              <div className="ws-table-wrap">
                <table className="ws-table">
                  <caption className="sr-only">Daftar {resource.label}</caption>
                  <thead>
                    <tr>
                      {resource.columns.map((c) => (
                        <th key={c}>{label(c)}</th>
                      ))}
                      <th>Rincian</th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data.data.map((row) => (
                      <tr key={row.id}>
                        {resource.columns.map((c, i) => (
                          <td key={c}>
                            {c.includes('status') ? (
                              <Status value={row[c]} />
                            ) : i === 0 ? (
                              <button className="ws-cell-link" onClick={() => setSelected(row)}>
                                {display(row[c])}
                              </button>
                            ) : (
                              display(row[c])
                            )}
                          </td>
                        ))}
                        <td>
                          <button
                            aria-label={'Lihat rincian ' + row.id}
                            className="ws-row-open"
                            onClick={() => setSelected(row)}
                          >
                            <ArrowUpRight size={17} />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <div className="ws-pagination">
              <span>
                {query.data.meta.from || 0}-{query.data.meta.to || 0} dari {query.data.meta.total}{' '}
                data
              </span>
              <div>
                <button
                  aria-label="Halaman sebelumnya"
                  disabled={page <= 1}
                  onClick={() => update({ page: String(page - 1) })}
                >
                  <ChevronLeft size={16} />
                </button>
                <span>
                  Halaman {query.data.meta.current_page} / {query.data.meta.last_page}
                </span>
                <button
                  aria-label="Halaman berikutnya"
                  disabled={page >= query.data.meta.last_page}
                  onClick={() => update({ page: String(page + 1) })}
                >
                  <ChevronRight size={16} />
                </button>
              </div>
            </div>
          </>
        )}
      </section>
      <p className="ws-register-note">
        <LockKeyhole size={14} /> Register ini menampilkan data sesuai hak akses. Keputusan dan
        pembekuan mengikuti workflow backend.
      </p>
      <Drawer
        open={!!selected}
        onClose={() => setSelected(null)}
        title={resource.label + ' #' + (selected?.id || '')}
        description="Rincian data tersimpan dan dokumen terkait."
      >
        {selected && (
          <RecordDetail key={resource.key + selected.id} row={selected} resource={resource} />
        )}
      </Drawer>
    </>
  )
}
function RecordDetail({ row, resource }: { row: Row; resource: Resource }) {
  const [related, setRelated] = useState<Resource | null>(null)
  const relations: Resource[] =
    resource.key === 'asesmen'
      ? [
          {
            key: 'risiko',
            label: 'Risiko',
            endpoint: '/api/asesmen/' + row.id + '/risiko',
            columns: ['kode', 'skenario'],
          },
          {
            key: 'rencana',
            label: 'Penanganan',
            endpoint: '/api/asesmen/' + row.id + '/rencana-penanganan',
            columns: ['kode', 'uraian', 'status', 'target_selesai'],
          },
          {
            key: 'laporan',
            label: 'Lampiran 14',
            endpoint: '/api/laporan/lampiran-14?asesmen_id=' + row.id,
            columns: ['kode', 'skenario'],
          },
        ]
      : ['dokumen', 'sop', 'soa'].includes(resource.key)
        ? [
            {
              key: 'versi',
              label: 'Riwayat versi',
              endpoint: '/api/' + resource.key + '/' + row.id + '/versi',
              columns: ['id', 'nomor_versi', 'status'],
            },
          ]
        : []
  const result = useQuery({
    queryKey: ['workspace', 'related', related?.endpoint],
    queryFn: ({ signal }) => getPage(related!.endpoint, { per_page: 100 }, signal),
    enabled: !!related,
  })
  const [relatedPage, setRelatedPage] = useState(1)
  const nextResult = useQuery({
    queryKey: ['workspace', 'related', related?.endpoint, relatedPage],
    queryFn: ({ signal }) =>
      getPage(related!.endpoint, { per_page: 100, page: relatedPage }, signal),
    enabled: !!related && relatedPage > 1,
  })
  const records = relatedPage > 1 ? nextResult : result
  return (
    <div className="ws-detail">
      <dl>
        {Object.entries(row)
          .filter(
            ([key]) =>
              !['data_mentah', 'hash_data', 'storage_key', 'checksum_sha256'].includes(key),
          )
          .map(([key, value]) => (
            <div key={key}>
              <dt>{label(key)}</dt>
              <dd>
                {key.includes('status') ? (
                  <Status value={value} />
                ) : typeof value === 'object' && value !== null ? (
                  <pre>{JSON.stringify(value, null, 2)}</pre>
                ) : (
                  display(value)
                )}
              </dd>
            </div>
          ))}
      </dl>
      {resource.key === 'file-lampiran' && (
        <button
          disabled={row.status_pemeriksaan !== 'AMAN'}
          onClick={() =>
            void download('/api/file-lampiran/' + row.id + '/unduh', String(row.nama_asli))
          }
        >
          <Download size={16} />
          Unduh lampiran
        </button>
      )}
      {relations.length > 0 && (
        <>
          <h3>Data terkait</h3>
          <div className="ws-tabs">
            {relations.map((r) => (
              <button
                key={r.key}
                aria-pressed={related?.key === r.key}
                onClick={() => {
                  setRelated(r)
                  setRelatedPage(1)
                }}
              >
                {r.label}
              </button>
            ))}
          </div>
        </>
      )}
      {related && (
        <>
          {related.key === 'laporan' && (
            <p>
              Data Lampiran 14 tersedia. Ekspor dokumen menunggu template laporan risiko yang
              sesuai.
            </p>
          )}
          {records.isPending ? (
            <Loading />
          ) : records.isError ? (
            <Failure error={records.error} retry={() => void records.refetch()} />
          ) : (
            <>
              <p>{records.data?.meta.total} data terkait</p>
              {records.data?.data.map((item) => (
                <details key={item.id} className="ws-related">
                  <summary>
                    {display(item.kode || item.judul || 'Data #' + item.id)}{' '}
                    {item.status ? <Status value={item.status} /> : null}
                  </summary>
                  <dl>
                    {Object.entries(item).map(([key, value]) => (
                      <div key={key}>
                        <dt>{label(key)}</dt>
                        <dd>
                          {typeof value === 'object' && value !== null ? (
                            <pre>{JSON.stringify(value, null, 2)}</pre>
                          ) : (
                            display(value)
                          )}
                        </dd>
                      </div>
                    ))}
                  </dl>
                </details>
              ))}
              <div className="ws-pagination">
                <button disabled={relatedPage === 1} onClick={() => setRelatedPage((p) => p - 1)}>
                  Sebelumnya
                </button>
                <span>
                  {relatedPage} / {records.data?.meta.last_page}
                </span>
                <button
                  disabled={relatedPage >= (records.data?.meta.last_page || 1)}
                  onClick={() => setRelatedPage((p) => p + 1)}
                >
                  Berikutnya
                </button>
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}
