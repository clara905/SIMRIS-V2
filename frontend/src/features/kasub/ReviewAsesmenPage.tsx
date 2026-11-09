import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, Check, ClipboardCheck, ExternalLink, ShieldCheck, X } from 'lucide-react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { useSession } from '../../api/auth'
import { api, errorMessage } from '../../api/client'
import { getOptions, getPage, type Row } from '../satker/api'
import { kasubReviewKeys } from './hooks'
import './ReviewAsesmenPage.css'

type ReviewItem = Row & {
  unit_id: number
  periode_id: number
  status: string
  nomor_versi: number
  tujuan: string
  dibuat_pada?: string
}
type ReviewStage = 'AWAL_KASUB' | 'AWAL_KABID' | 'AWAL_KASATKER' | 'AKHIR_KASUB' | 'AKHIR_KABID' | 'AKHIR_KASATKER' | 'KASUB_AWAL' | 'KABID_AWAL' | 'KASUB_RESIDUAL' | 'KABID_RESIDUAL' | 'KASATKER_FINAL'
type ReturnTarget = 'PENANGANAN' | 'RESIDUAL_DRAFT'
type ReviewRecord = Row & { tahap: string; keputusan: string }
type ReviewStageItem = { stage: ReviewStage; label: string; role: 'KASUB' | 'KABID' | 'KASATKER' }
type ReviewQueueItem = ReviewItem & { reviewStage: ReviewStageItem }
type DecisionInput = { item: ReviewQueueItem; decision: 'SETUJU' | 'TOLAK'; note: string; returnTo?: ReturnTarget }

const legacyStages: Record<string, ReviewStageItem> = {
  MENUNGGU_KASUB: { stage: 'KASUB_AWAL', label: 'Review awal Kasub', role: 'KASUB' },
  VERIFIKASI_KASUB: { stage: 'KASUB_RESIDUAL', label: 'Verifikasi residual Kasub', role: 'KASUB' },
  MENUNGGU_KABID: { stage: 'KABID_AWAL', label: 'Review awal Kabid', role: 'KABID' },
  VERIFIKASI_KABID: { stage: 'KABID_RESIDUAL', label: 'Verifikasi residual Kabid', role: 'KABID' },
  MENUNGGU_KASATKER: { stage: 'KASATKER_FINAL', label: 'Persetujuan Kasatker', role: 'KASATKER' },
}
const statusNames: Record<string, string> = {
  DIAJUKAN: 'Menunggu persetujuan',
  MENUNGGU_KASUB: 'Menunggu review Kasub',
  VERIFIKASI_KASUB: 'Menunggu verifikasi Kasub',
  MENUNGGU_KABID: 'Menunggu persetujuan Kabid',
  VERIFIKASI_KABID: 'Menunggu verifikasi Kabid',
  MENUNGGU_KASATKER: 'Menunggu persetujuan Kasatker',
}

function statusesFor(roles: string[]): string[] {
  const legacy = Object.entries(legacyStages)
    .filter(([, stage]) => roles.includes(stage.role))
    .map(([status]) => status)
  return roles.some((role) => ['KASUB', 'KABID', 'KASATKER'].includes(role))
    ? ['DIAJUKAN', ...legacy]
    : legacy
}

function nextReviewStage(reviews: ReviewRecord[]): ReviewStageItem | null {
  const initialDone = reviews.some((review) => review.tahap === 'AWAL_KASATKER' && review.keputusan === 'SETUJU')
  const prefix = initialDone ? 'AKHIR_' : 'AWAL_'
  const sequence: ReviewStageItem[] = initialDone
    ? [
      { stage: 'AKHIR_KASUB', label: 'Review akhir Kasub', role: 'KASUB' },
      { stage: 'AKHIR_KABID', label: 'Review akhir Kabid', role: 'KABID' },
      { stage: 'AKHIR_KASATKER', label: 'Persetujuan akhir Kasatker', role: 'KASATKER' },
    ]
    : [
      { stage: 'AWAL_KASUB', label: 'Review awal Kasub', role: 'KASUB' },
      { stage: 'AWAL_KABID', label: 'Review awal Kabid', role: 'KABID' },
      { stage: 'AWAL_KASATKER', label: 'Persetujuan awal Kasatker', role: 'KASATKER' },
    ]
  const lastRejectionId = Math.max(0, ...reviews.filter((review) => review.tahap.startsWith(prefix) && review.keputusan === 'TOLAK').map((review) => review.id))
  const approved = new Set(reviews.filter((review) => review.tahap.startsWith(prefix) && review.keputusan === 'SETUJU' && review.id > lastRejectionId).map((review) => review.tahap))
  return sequence.find((stage) => !approved.has(stage.stage)) ?? null
}

async function loadQueue(statuses: string[], roles: string[], signal?: AbortSignal): Promise<ReviewQueueItem[]> {
  const results = await Promise.all(statuses.map(async (status) => {
    const firstPage = await getPage('/api/asesmen', { per_page: 100, page: 1, 'filter[status]': status }, signal)
    const items = [...firstPage.data]
    for (let page = 2; page <= firstPage.meta.last_page; page += 1) {
      const next = await getPage('/api/asesmen', { per_page: 100, page, 'filter[status]': status }, signal)
      items.push(...next.data)
    }
    const stagedItems = await Promise.all((items as ReviewItem[]).map(async (item): Promise<ReviewQueueItem | null> => {
      const reviewStage = status === 'DIAJUKAN'
        ? nextReviewStage((await getPage(`/api/asesmen/${item.id}/review`, { per_page: 100 }, signal)).data as ReviewRecord[])
        : legacyStages[status]
      return reviewStage && roles.includes(reviewStage.role) ? { ...item, reviewStage } : null
    }))
    return stagedItems.filter((item): item is ReviewQueueItem => item !== null)
  }))
  return results.flat().sort((a, b) => String(b.dibuat_pada ?? '').localeCompare(String(a.dibuat_pada ?? '')))
}

function assignedStages(roles: string[]) {
  return statusesFor(roles).flatMap((status) => status === 'DIAJUKAN'
    ? ['Persetujuan awal/akhir sesuai urutan']
    : [legacyStages[status].label])
}

export function ReviewAsesmenPage() {
  const session = useSession()
  const queryClient = useQueryClient()
  const [notes, setNotes] = useState<Record<number, string>>({})
  const [returnTargets, setReturnTargets] = useState<Record<number, ReturnTarget | ''>>({})
  const roles = session.data?.roleCodes ?? []
  const statuses = statusesFor(roles)
  const queueKey = kasubReviewKeys.assessments(roles.slice().sort().join(',') || 'tanpa-role')
  const queue = useQuery({
    queryKey: queueKey,
    queryFn: ({ signal }) => loadQueue(statuses, roles, signal),
    enabled: statuses.length > 0,
  })
  const units = useQuery({
    queryKey: ['kasub-review', 'units'],
    queryFn: ({ signal }) => getOptions('/api/unit-organisasi', (row) => String(row.nama || row.kode || `#${row.id}`), signal),
    enabled: statuses.length > 0,
    staleTime: 5 * 60 * 1000,
  })
  const decide = useMutation({
    mutationFn: async ({ item, decision, note, returnTo }: DecisionInput) => {
      return api.post(`/api/asesmen/${item.id}/review`, {
        tahap: item.reviewStage.stage,
        keputusan: decision,
        catatan: note.trim() || null,
        kembali_ke: decision === 'TOLAK' && ['KASUB_RESIDUAL', 'KABID_RESIDUAL'].includes(item.reviewStage.stage)
          ? returnTo
          : undefined,
      })
    },
    onSuccess: async (_response, variables) => {
      toast.success(variables.decision === 'SETUJU' ? 'Asesmen disetujui.' : 'Asesmen ditolak.')
      await queryClient.invalidateQueries({ queryKey: kasubReviewKeys.root })
      await queryClient.invalidateQueries({ queryKey: ['asesmen-detail', variables.item.id] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const unitNames = new Map((units.data ?? []).map((unit) => [unit.id, unit.label]))
  const submitDecision = (item: ReviewQueueItem, decision: 'SETUJU' | 'TOLAK') => {
    const note = notes[item.id] ?? ''
    if (decision === 'TOLAK' && !note.trim()) {
      toast.error('Catatan wajib diisi untuk menolak asesmen.')
      return
    }
    const needsReturn = decision === 'TOLAK' && ['KASUB_RESIDUAL', 'KABID_RESIDUAL'].includes(item.reviewStage.stage)
    const returnTo = returnTargets[item.id]
    if (needsReturn && !returnTo) {
      toast.error('Pilih tujuan pengembalian asesmen.')
      return
    }
    decide.mutate({ item, decision, note, returnTo: returnTo || undefined })
  }

  return <div className="kasub-review-page">
    <header className="kasub-review-hero">
      <div>
        <span className="ws-eyebrow">Review workflow</span>
        <h1>Review asesmen</h1>
        <p>Antrean ditentukan dari role aktif dan scope unit Anda. Keputusan tetap diverifikasi backend.</p>
      </div>
      <aside className="kasub-review-scope" aria-label="Batas kewenangan reviewer">
        <ShieldCheck size={21} aria-hidden="true" />
        <div><strong>Lingkup terlindungi</strong><span>{assignedStages(roles).join(' · ') || 'Tidak ada role reviewer aktif'}</span></div>
      </aside>
    </header>

    <section className="kasub-review-workflow" aria-label="Alur review">
      <div className="kasub-review-flow-step"><span>01</span><div><strong>Periksa asesmen</strong><p>Buka risiko, penilaian, dan usulan.</p></div></div>
      <ArrowRight aria-hidden="true" />
      <div className="kasub-review-flow-step"><span>02</span><div><strong>Beri keputusan</strong><p>Penolakan wajib disertai catatan.</p></div></div>
      <ArrowRight aria-hidden="true" />
      <div className="kasub-review-flow-step"><span>03</span><div><strong>Teruskan workflow</strong><p>Perubahan status mengikuti alur backend.</p></div></div>
    </section>

    <section className="ws-panel kasub-review-queue" aria-label="Antrean review asesmen">
      <header><div><span className="ws-eyebrow">Antrean saya</span><h2>Asesmen yang memerlukan review</h2></div><ClipboardCheck size={23} aria-hidden="true" /></header>
      {session.isPending || queue.isPending ? <p className="kasub-review-loading" role="status">Memuat antrean asesmen…</p>
        : session.isError || queue.isError ? <div className="kasub-review-unavailable" role="alert"><ShieldCheck size={30} /><strong>Antrean gagal dimuat</strong><p>{errorMessage(queue.error || session.error)}</p></div>
          : statuses.length === 0 ? <div className="kasub-review-unavailable" role="status"><ShieldCheck size={30} /><strong>Role reviewer tidak tersedia</strong><p>Akun aktif Anda belum memiliki role Kasub, Kabid, atau Kasatker.</p></div>
            : queue.data?.length ? <div className="kasub-review-list">
              {queue.data.map((item) => {
                const busy = decide.isPending && decide.variables?.item.id === item.id
                const requiresReturnTarget = ['KASUB_RESIDUAL', 'KABID_RESIDUAL'].includes(item.reviewStage.stage)
                return <article className="kasub-review-item" key={item.id}>
                  <div className="kasub-review-item-heading">
                    <div><span className="ws-eyebrow">{item.reviewStage.label}</span><h3>Asesmen #{item.id} · Versi {item.nomor_versi}</h3></div>
                    <span className="ws-badge neutral"><span />{statusNames[item.status] ?? item.status}</span>
                  </div>
                  <p>{item.tujuan || 'Tujuan asesmen belum tersedia.'}</p>
                  <div className="kasub-review-item-meta"><span>Unit: {unitNames.get(item.unit_id) ?? `#${item.unit_id}`}</span><span>Periode: #{item.periode_id}</span></div>
                  {units.isError && <p className="kasub-review-reference-error" role="alert">Nama unit tidak dapat dimuat: {errorMessage(units.error)}</p>}
                  <Link className="kasub-review-detail-link" to={`/asesmen/${item.id}`}>Buka detail asesmen <ExternalLink size={14} /></Link>
                  <label className="kasub-review-note">Catatan review
                    <textarea maxLength={10000} value={notes[item.id] ?? ''} onChange={(event) => setNotes((current) => ({ ...current, [item.id]: event.target.value }))} disabled={decide.isPending} placeholder="Catatan wajib untuk keputusan tolak" />
                  </label>
                  {requiresReturnTarget && <label className="kasub-review-note">Tujuan jika ditolak
                    <select value={returnTargets[item.id] ?? ''} onChange={(event) => {
                      const value = event.target.value
                      if (value === '' || value === 'PENANGANAN' || value === 'RESIDUAL_DRAFT') {
                        setReturnTargets((current) => ({ ...current, [item.id]: value }))
                      }
                    }} disabled={decide.isPending}>
                      <option value="">Pilih tujuan pengembalian</option>
                      <option value="PENANGANAN">Penanganan</option>
                      <option value="RESIDUAL_DRAFT">Draft residual</option>
                    </select>
                  </label>}
                  <div className="kasub-review-actions">
                    <button className="primary" type="button" disabled={decide.isPending} onClick={() => submitDecision(item, 'SETUJU')}><Check size={15} />{busy && decide.variables?.decision === 'SETUJU' ? 'Menyimpan…' : 'Setujui'}</button>
                    <button type="button" disabled={decide.isPending} onClick={() => submitDecision(item, 'TOLAK')}><X size={15} />{busy && decide.variables?.decision === 'TOLAK' ? 'Menyimpan…' : 'Tolak'}</button>
                  </div>
                </article>
              })}
            </div> : <div className="kasub-review-unavailable" role="status"><ClipboardCheck size={31} /><strong>Tidak ada asesmen dalam antrean</strong><p>Antrean akan terisi saat asesmen pada scope Anda diajukan atau menunggu verifikasi.</p></div>}
      <footer className="kasub-review-status" aria-label="Tahap workflow reviewer">
        <span>Status antrean</span><div>{statuses.map((status) => <span className="ws-badge neutral" key={status}><span />{statusNames[status]}</span>)}</div>
      </footer>
    </section>
  </div>
}
