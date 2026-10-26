import { useQueries } from '@tanstack/react-query'
import { AlertTriangle, ArrowUpRight, Boxes, FileCheck2, ShieldCheck } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useSession } from '../../api/auth'
import { countOf } from './api'

const cards = [
  {
    label: 'Aset satker',
    endpoint: '/api/aset',
    icon: Boxes,
    path: '/aset',
    description: 'Seluruh aset dalam satker Anda',
  },
  {
    label: 'Aset sudah dinilai',
    endpoint: '/api/aset',
    filter: { perlu_ditinjau: false },
    icon: Boxes,
    path: '/aset?tab=aset-dinilai',
    description: 'Aset yang sudah ditinjau dalam seluruh bidang',
  },
  {
    label: 'Aset belum dinilai',
    endpoint: '/api/aset',
    filter: { perlu_ditinjau: true },
    icon: AlertTriangle,
    path: '/aset?tab=aset-belum-dinilai',
    description: 'Menunggu penilaian risiko',
  },
  {
    label: 'Dokumen',
    endpoint: '/api/dokumen',
    icon: FileCheck2,
    path: '/dokumen',
    description: 'Dokumen milik satker Anda',
  },
  {
    label: 'SOP',
    endpoint: '/api/sop',
    icon: ShieldCheck,
    path: '/kepatuhan?tab=sop',
    description: 'Prosedur operasi baku',
  },
  {
    label: 'Kontrol internal',
    endpoint: '/api/kontrol-internal',
    icon: ShieldCheck,
    path: '/kepatuhan?tab=kontrol-internal',
    description: 'Kontrol yang dikelola satker',
  },
]

const actions = [
  { path: '/kelola/aset', label: 'Kelola aset & penugasan', text: 'Ubah data aset dan tetapkan penanggung jawab', icon: Boxes },
  { path: '/kelola/dokumen', label: 'Kelola dokumen', text: 'Susun dokumen, versi, dan lampiran', icon: FileCheck2 },
  { path: '/kelola/kepatuhan', label: 'Kelola SOP & kontrol internal', text: 'Susun draft dan ajukan untuk ditinjau', icon: ShieldCheck },
]

/** Beranda khusus Admin Satker: ringkasan data satkernya dan jalan pintas ke menu pengelolaan. */
export function SatkerBeranda() {
  const session = useSession()
  const counts = useQueries({
    queries: cards.map((c) => ({
      queryKey: ['satker', 'count', c.endpoint, c.filter],
      queryFn: ({ signal }: { signal: AbortSignal }) => countOf(c.endpoint, c.filter, signal),
    })),
  })

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">ADMIN SATKER</span>
          <h1>Beranda satker</h1>
          <p>Selamat datang, {session.data?.nama}. Kelola aset, dokumen, dan SOP satker Anda.</p>
        </div>
      </div>
      <section className="ws-metrics" aria-label="Ringkasan data satker">
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
                  : counts[i].data?.toLocaleString('id-ID')}
            </strong>
            <small>
              {counts[i].isError ? 'Buka modul untuk mencoba lagi' : card.description}
              <ArrowUpRight size={14} />
            </small>
          </Link>
        ))}
      </section>
      <div className="ws-quick-links">
        {actions.map((item) => (
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
