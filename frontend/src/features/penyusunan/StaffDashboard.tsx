import { useQuery, useQueries } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { ArrowRight, ClipboardList, Clock3, FilePlus2, RotateCcw, ShieldAlert } from 'lucide-react'
import { Link } from 'react-router-dom'
import { errorMessage } from '../../api/client'
import { useSession } from '../../api/auth'
import { getPage, type Page } from '../satker/api'
import './staff-dashboard.css'

const workStates = [
  { status: 'DRAFT', label: 'Draft perlu dilengkapi', icon: ClipboardList, tone: 'draft' },
  { status: 'RESIDUAL_DRAFT', label: 'Residual perlu dilengkapi', icon: Clock3, tone: 'residual' },
  { status: 'DITOLAK', label: 'Perlu direvisi', icon: RotateCcw, tone: 'rejected' },
] as const

function formatStatus(value: unknown) {
  return String(value ?? '-').replaceAll('_', ' ')
}

function QueueState({ page, error, empty, children }: { page: Page | undefined; error: unknown; empty: string; children: () => ReactNode }) {
  if (error) return <div className="ws-empty" role="alert"><strong>Antrean belum tersedia</strong><p>{errorMessage(error)}</p></div>
  if (!page) return <div className="ws-staff-lines" role="status" aria-label="Memuat antrean"><i /><i /><i /></div>
  if (!page.data.length) return <div className="ws-empty"><strong>{empty}</strong></div>
  return <>{children()}</>
}

/** Beranda kerja personal: hanya dipasang untuk akun STAFF tanpa persona operasional lain. */
export function StaffDashboard() {
  const session = useSession()
  const counts = useQueries({
    queries: workStates.map(({ status }) => ({
      queryKey: ['staff-work', 'count', status],
      queryFn: ({ signal }: { signal: AbortSignal }) => getPage('/api/asesmen', { per_page: 1, filter: { status } }, signal),
    })),
  })
  const queue = useQuery({
    queryKey: ['staff-work', 'assessments'],
    queryFn: ({ signal }) => getPage('/api/asesmen', { per_page: 8, sort: '-id' }, signal),
  })
  const openWork = (queue.data?.data ?? []).filter((row) => ['DRAFT', 'RESIDUAL_DRAFT', 'DITOLAK'].includes(String(row.status)))

  return (
    <div className="ws-staff-dashboard">
      <section className="ws-staff-intro" aria-labelledby="staff-dashboard-title">
        <div>
          <span className="ws-eyebrow">RUANG KERJA STAFF</span>
          <h1 id="staff-dashboard-title">Pekerjaan saya</h1>
          <p>Selamat datang, {session.data?.nama}. Lengkapi asesmen Anda dan pantau pekerjaan yang perlu ditindaklanjuti.</p>
        </div>
        <Link className="ws-staff-create" to="/buat-asesmen"><FilePlus2 size={18} /> Buat asesmen</Link>
      </section>

      <section className="ws-staff-queue" aria-label="Ringkasan pekerjaan asesmen">
        {workStates.map(({ status, label, icon: Icon, tone }, index) => {
          const result = counts[index]
          return <Link to={`/asesmen-saya?status=${status}`} className={`ws-staff-count ${tone}`} key={status}>
            <Icon size={20} />
            <div><span>{label}</span><strong>{result.isPending ? '…' : result.isError ? '–' : result.data?.meta.total.toLocaleString('id-ID')}</strong></div>
            <ArrowRight size={16} aria-hidden="true" />
          </Link>
        })}
      </section>

      <div className="ws-staff-grid">
        <section className="ws-panel">
          <header className="ws-panel-heading">
            <div><h2>Yang perlu Anda kerjakan</h2><p>Hanya asesmen yang Anda buat sendiri.</p></div>
            <Link to="/asesmen-saya">Semua asesmen <ArrowRight size={15} /></Link>
          </header>
          <QueueState page={queue.data} error={queue.error} empty="Tidak ada draft atau revisi yang perlu dikerjakan.">
            {() => openWork.length ? <div className="ws-staff-list">
              {openWork.map((item) => <Link key={item.id} to={`/asesmen/${item.id}`}>
                <span className={`ws-staff-status ${String(item.status).toLowerCase()}`}>{formatStatus(item.status)}</span>
                <strong>{String(item.tujuan || `Asesmen #${item.id}`)}</strong>
                <small>Versi {String(item.nomor_versi ?? 1)} <ArrowRight size={14} /></small>
              </Link>)}
            </div> : <div className="ws-empty"><strong>Tidak ada pekerjaan asesmen yang terbuka.</strong></div>}
          </QueueState>
        </section>

        <aside className="ws-staff-note">
          <ShieldAlert size={22} />
          <div><h2>Penanganan sebagai PIC</h2><p>Server belum menyediakan daftar rencana lintas asesmen yang dapat difilter menurut PIC Anda. Antrean ini tidak ditampilkan agar tidak memberi kesan sebagai daftar yang otoritatif.</p></div>
          <Link to="/asesmen-saya">Buka asesmen saya <ArrowRight size={15} /></Link>
        </aside>
      </div>
    </div>
  )
}
