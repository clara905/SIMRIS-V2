import { useQueries } from '@tanstack/react-query'
import {
  ArrowUpRight,
  Boxes,
  ContactRound,
  FileText,
  Network,
  ShieldCheck,
  UsersRound,
} from 'lucide-react'
import { Link } from 'react-router-dom'
import { useSession } from '../../api/auth'
import { countOf, useSatkerUnit } from './api'

type MetricCard = {
  label: string
  endpoint: string
  filter?: Record<string, unknown>
  icon: typeof Boxes
  path: string
  description: string
}

const cards: MetricCard[] = [
  {
    label: 'Aset satker',
    endpoint: '/api/aset',
    icon: Boxes,
    path: '/kelola/aset',
    description: 'Inventaris aset dalam satker Anda',
  },
  {
    label: 'Aset belum dinilai',
    endpoint: '/api/aset',
    filter: { perlu_ditinjau: true },
    icon: Boxes,
    path: '/kelola/aset',
    description: 'Menunggu peninjauan dan penetapan lokal',
  },
  {
    label: 'Dokumen',
    endpoint: '/api/dokumen',
    icon: FileText,
    path: '/kelola/dokumen',
    description: 'Dokumen dan bukti pendukung',
  },
  {
    label: 'SOP',
    endpoint: '/api/sop',
    icon: ShieldCheck,
    path: '/kelola/kepatuhan',
    description: 'Standar operasional prosedur satker',
  },
  {
    label: 'Kontrol internal',
    endpoint: '/api/kontrol-internal',
    icon: ShieldCheck,
    path: '/kelola/kepatuhan',
    description: 'Penerapan kontrol internal',
  },
  {
    label: 'Pengguna',
    endpoint: '/api/users',
    icon: UsersRound,
    path: '/kelola/pengguna',
    description: 'Akun dan hak akses dalam satker Anda',
  },
  {
    label: 'Pegawai',
    endpoint: '/api/pegawai',
    icon: ContactRound,
    path: '/kelola/pegawai',
    description: 'Data pegawai dan penempatan satker Anda',
  },
]

const quickLinks = [
  {
    label: 'Kelola unit',
    path: '/kelola/unit',
    icon: Network,
    description: 'Struktur organisasi dan unit turunan',
  },
  {
    label: 'Kelola pegawai',
    path: '/kelola/pegawai',
    icon: ContactRound,
    description: 'Data pegawai dan penempatan',
  },
  {
    label: 'Kelola pengguna',
    path: '/kelola/pengguna',
    icon: UsersRound,
    description: 'Akun dan role operasional staf',
  },
  {
    label: 'Kelola aset & penugasan',
    path: '/kelola/aset',
    icon: Boxes,
    description: 'Data lokal aset dan penanggung jawab',
  },
  {
    label: 'Kelola dokumen',
    path: '/kelola/dokumen',
    icon: FileText,
    description: 'Dokumen, versi draft, dan lampiran',
  },
  {
    label: 'Kelola SOP & kontrol internal',
    path: '/kelola/kepatuhan',
    icon: ShieldCheck,
    description: 'Penyusunan SOP dan kontrol internal',
  },
]

/** Beranda khusus Admin Satker untuk pengelolaan satker terintegrasi. */
export function SatkerBeranda() {
  const session = useSession()
  const satker = useSatkerUnit()
  const satkerName = satker.data?.nama ? String(satker.data.nama) : 'Satker'

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
          <span className="ws-eyebrow">ADMIN SATKER — {satkerName.toUpperCase()}</span>
          <h1>Beranda {satkerName}</h1>
          <p>
            Selamat datang, {session.data?.nama}. Kelola unit, pegawai, pengguna, aset, dokumen, dan
            kepatuhan satker Anda.
          </p>
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
        {quickLinks.map((item) => (
          <Link to={item.path} key={item.path}>
            <item.icon size={25} />
            <div>
              <strong>{item.label}</strong>
              <small>{item.description}</small>
            </div>
            <ArrowUpRight size={18} />
          </Link>
        ))}
      </div>
    </>
  )
}
