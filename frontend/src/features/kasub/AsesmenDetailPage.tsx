import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, ClipboardCheck, FilePlus2, Save, Send, ShieldAlert } from 'lucide-react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { useSession } from '../../api/auth'
import { MatriksGrid } from '../../components/MatriksGrid'
import { byKodeNama, getOptions, getPage, type Option, type Row } from '../satker/api'
import './AsesmenDetailPage.css'

type Assessment = Row & {
  unit_id: number
  periode_id: number
  versi_metode_id: number
  dibuat_oleh: number
  nomor_versi: number
  tujuan: string
  ruang_lingkup: string
  status: string
}
type Rating = Row & {
  tahap: string
  matriks_id: number
  skor?: number
  perlu_rtp?: boolean | null
  perlu_persetujuan_pemilik?: boolean | null
  alasan_kemungkinan: string
  alasan_dampak: string
  kesimpulan: string
  alasan_perubahan_level?: string | null
  matriks?: (Row & { skor: number; kemungkinan_id: number; dampak_id: number; tingkat_risiko_id: number }) | null
  tingkat?: RiskLevel | null
  kemungkinan?: RiskScale | null
  dampak?: RiskScale | null
}
type Proposal = Row & { keputusan: string; alasan: string }
type Review = Row & { reviewer_id: number; tahap: string; keputusan: string; catatan: string | null; waktu: string }
type HandlingPlan = Row & { kode: string; uraian: string; pic_id: number; target_selesai: string; status: string }
type HandlingProgress = Row & { uraian: string; status: string; dilaporkan_pada: string }
type RiskScale = Row & { nilai: number; label?: string; nama?: string; kriteria: string }
type RiskLevel = Row & {
  kode: string
  nama: string
  warna: string
  batas_min_skor: number | null
  batas_max_skor: number | null
  perlu_rtp: boolean | null
  perlu_persetujuan_pemilik: boolean | null
  kriteria_penerimaan: string | null
}
type Risk = Row & {
  kode: string
  ancaman_id: number
  kerentanan_id: number
  area_dampak_id: number
  pemilik_risiko_id: number
  skenario: string
  uraian_kerentanan: string
  uraian_dampak: string
  arah_dampak: string
  konteks?: string | null
  konsekuensi_kerahasiaan?: string | null
  konsekuensi_integritas?: string | null
  konsekuensi_ketersediaan?: string | null
  konsekuensi_kepatuhan?: string | null
  konsekuensi_operasional?: string | null
  asesmen_aset_ids?: number[]
  ratings: Rating[]
  proposals: Proposal[]
}
type RiskMapRisk = Row & {
  id: number
  kode: string
  ancaman_id: number
  kerentanan_id: number
  area_dampak_id: number
  pemilik_risiko_id: number
  skenario: string
  uraian_kerentanan: string
  uraian_dampak: string
  arah_dampak: string
  konteks?: string | null
  konsekuensi_kerahasiaan?: string | null
  konsekuensi_integritas?: string | null
  konsekuensi_ketersediaan?: string | null
  konsekuensi_kepatuhan?: string | null
  konsekuensi_operasional?: string | null
  asesmen_aset_ids: number[]
  penilaian: Rating[]
  usulan_keputusan: Proposal[]
}
type RiskMap = {
  metode: {
    id: number
    nama: string
    versi: string | number
    profil_penilaian: boolean
    kemungkinan: RiskScale[]
    dampak: RiskScale[]
    tingkat: RiskLevel[]
    matriks: (Row & { kemungkinan_id: number; dampak_id: number; skor: number; tingkat_risiko_id: number })[]
  }
  peta: Record<string, Record<number, { matriks_id: number; kemungkinan_id: number; dampak_id: number; jumlah: number; risiko_ids: number[] }>>
  aset: (Row & { id: number; aset_id: number; nama_snapshot: string; nilai_kekritisan: number; dasar_kekritisan: string })[]
  risiko: RiskMapRisk[]
}
type Detail = { assessment: Assessment; risks: Risk[]; reviews: Review[]; plans: HandlingPlan[]; riskMap: RiskMap }
type RiskForm = {
  ancaman_id: string
  kerentanan_id: string
  area_dampak_id: string
  pemilik_risiko_id: string
  skenario: string
  uraian_kerentanan: string
  uraian_dampak: string
  arah_dampak: string
  konteks: string
  konsekuensi_kerahasiaan: string
  konsekuensi_integritas: string
  konsekuensi_ketersediaan: string
  konsekuensi_kepatuhan: string
  konsekuensi_operasional: string
  asesmen_aset_ids: string[]
}

const emptyRisk: RiskForm = {
  ancaman_id: '',
  kerentanan_id: '',
  area_dampak_id: '',
  pemilik_risiko_id: '',
  skenario: '',
  uraian_kerentanan: '',
  uraian_dampak: '',
  arah_dampak: 'N',
  konteks: '',
  konsekuensi_kerahasiaan: '',
  konsekuensi_integritas: '',
  konsekuensi_ketersediaan: '',
  konsekuensi_kepatuhan: '',
  konsekuensi_operasional: '',
  asesmen_aset_ids: [],
}
const statusNames: Record<string, string> = {
  DRAFT: 'Draft',
  DIAJUKAN: 'Menunggu review',
  DIREVIU: 'Menunggu penanganan',
  MENUNGGU_KASUB: 'Menunggu review Kasub',
  MENUNGGU_KABID: 'Menunggu review Kabid',
  AWAL_SELESAI: 'Menunggu penanganan',
  PENANGANAN: 'Dalam penanganan',
  RESIDUAL_DRAFT: 'Draft residual',
  VERIFIKASI_KASUB: 'Menunggu verifikasi Kasub',
  VERIFIKASI_KABID: 'Menunggu verifikasi Kabid',
  MENUNGGU_KASATKER: 'Menunggu pengesahan',
  DISAHKAN: 'Disahkan',
  DITOLAK: 'Ditolak',
  KEMBALI: 'Dikembalikan',
}

async function loadDetail(id: number): Promise<Detail> {
  const [mapResponse, reviewPage, planPage] = await Promise.all([
    api.get<{ data: RiskMap & { asesmen: Assessment } }>(`/api/asesmen/${id}/peta-risiko`),
    getPage(`/api/asesmen/${id}/review`, { per_page: 100 }),
    getPage(`/api/asesmen/${id}/rencana-penanganan`, { per_page: 100 }),
  ])
  const riskMap = mapResponse.data.data
  const risks = riskMap.risiko.map((risk) => ({
    ...risk,
    ratings: risk.penilaian,
    proposals: risk.usulan_keputusan,
  }))
  return { assessment: riskMap.asesmen, risks, reviews: reviewPage.data as Review[], plans: planPage.data as HandlingPlan[], riskMap }
}

function optionRows(rows: Row[] | undefined, names?: { likelihood: Map<number, string>; impact: Map<number, string>; level: Map<number, string> }): Option[] {
  return (rows ?? []).map((row) => ({
    id: row.id,
    label: names && 'kemungkinan_id' in row
      ? `${names.likelihood.get(Number(row.kemungkinan_id)) ?? '?'} × ${names.impact.get(Number(row.dampak_id)) ?? '?'} · ${names.level.get(Number(row.tingkat_risiko_id)) ?? '?'} (skor ${String(row.skor ?? '?')})`
      : String(row.nama || row.label || row.kode || `#${row.id}`),
  }))
}

function scaleName(scale: RiskScale): string {
  return scale.label ?? scale.nama ?? `Skala ${scale.nilai}`
}

export function RatingEditor({
  riskId,
  stage,
  rating,
  matrices,
  matrixRows,
  likelihoods,
  impacts,
  levels,
  profile,
  editable,
  refresh,
  finalVersions = [],
}: {
  riskId: number
  stage: 'SAAT_INI' | 'TARGET_SETELAH' | 'AKTUAL_SETELAH'
  rating?: Rating
  matrices: Option[]
  matrixRows: RiskMap['metode']['matriks']
  likelihoods: RiskScale[]
  impacts: RiskScale[]
  levels: RiskLevel[]
  profile: boolean
  editable: boolean
  refresh: () => void
  finalVersions?: Option[]
}) {
  const [matrixId, setMatrixId] = useState(String(rating?.matriks_id ?? ''))
  const [likelihood, setLikelihood] = useState(String(rating?.kemungkinan?.nilai ?? ''))
  const [impact, setImpact] = useState(String(rating?.dampak?.nilai ?? ''))
  const [reasonLikelihood, setReasonLikelihood] = useState(rating?.alasan_kemungkinan ?? '')
  const [reasonImpact, setReasonImpact] = useState(rating?.alasan_dampak ?? '')
  const [conclusion, setConclusion] = useState(rating?.kesimpulan ?? '')
  const [levelReason, setLevelReason] = useState(rating?.alasan_perubahan_level ?? '')
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const previewMatrix = matrixRows.find((cell) =>
    Number(cell.kemungkinan_id) === likelihoods.find((scale) => scale.nilai === Number(likelihood))?.id &&
    Number(cell.dampak_id) === impacts.find((scale) => scale.nilai === Number(impact))?.id,
  )
  const previewLevel = levels.find((level) => level.id === previewMatrix?.tingkat_risiko_id)
  const save = useMutation({
    mutationFn: async () => api.post(`/api/risiko/${riskId}/penilaian`, {
      ...(profile
        ? { kemungkinan_nilai: Number(likelihood), dampak_nilai: Number(impact) }
        : { matriks_id: Number(matrixId) }),
      tahap: stage,
      alasan_kemungkinan: reasonLikelihood,
      alasan_dampak: reasonImpact,
      kesimpulan: conclusion,
      ...(stage === 'TARGET_SETELAH' ? { alasan_perubahan_level: levelReason } : {}),
    }),
    onSuccess: () => { setErrors({}); toast.success(`Penilaian ${stage === 'SAAT_INI' ? 'saat ini' : 'target'} tersimpan.`); refresh() },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) },
  })
  const fieldError = (name: string) => errors[name]?.[0]

  return <form className="assessment-rating" onSubmit={(event) => { event.preventDefault(); save.mutate() }}>
    <h4>{stage === 'SAAT_INI' ? 'Penilaian saat ini' : stage === 'TARGET_SETELAH' ? 'Penilaian target setelah RTP' : 'Penilaian aktual setelah penanganan'}</h4>
    {!editable && !rating ? <p className="assessment-muted">Belum ada penilaian.</p> : null}
    {rating && <p className="assessment-rating-current">{matrices.find((matrix) => matrix.id === rating.matriks_id)?.label ?? `Matriks #${rating.matriks_id}`} · {rating.kesimpulan}</p>}
    {rating && <p className="assessment-rating-current">Nilai server: {rating.tingkat?.nama ?? 'Tingkat belum tersedia'} · skor {rating.skor ?? rating.matriks?.skor ?? '—'}</p>}
    {editable && <>
      {profile ? <>
        <label>Kemungkinan
          <select required value={likelihood} onChange={(event) => setLikelihood(event.target.value)} disabled={save.isPending}>
            <option value="">Pilih kemungkinan</option>{likelihoods.map((scale) => <option key={scale.id} value={scale.nilai}>{scale.nilai} · {scaleName(scale)}</option>)}
          </select>
          {likelihoods.find((scale) => scale.nilai === Number(likelihood))?.kriteria && <small>{likelihoods.find((scale) => scale.nilai === Number(likelihood))?.kriteria}</small>}
          {fieldError('kemungkinan_nilai') && <small role="alert">{fieldError('kemungkinan_nilai')}</small>}
        </label>
        <label>Dampak
          <select required value={impact} onChange={(event) => setImpact(event.target.value)} disabled={save.isPending}>
            <option value="">Pilih dampak</option>{impacts.map((scale) => <option key={scale.id} value={scale.nilai}>{scale.nilai} · {scaleName(scale)}</option>)}
          </select>
          {impacts.find((scale) => scale.nilai === Number(impact))?.kriteria && <small>{impacts.find((scale) => scale.nilai === Number(impact))?.kriteria}</small>}
          {fieldError('dampak_nilai') && <small role="alert">{fieldError('dampak_nilai')}</small>}
        </label>
        {likelihood && impact && <p className="assessment-rating-preview" aria-live="polite">
          Pratinjau (belum disimpan): skor {previewMatrix?.skor ?? 'belum tersedia'} · {previewLevel?.nama ?? 'tingkat belum tersedia'}
          {previewLevel?.perlu_rtp && <strong> · Wajib RTP</strong>}
        </p>}
      </> : <label>Matriks risiko
        <select required value={matrixId} onChange={(event) => setMatrixId(event.target.value)} disabled={save.isPending}>
          <option value="">Pilih kemungkinan × dampak</option>
          {matrices.map((matrix) => <option key={matrix.id} value={matrix.id}>{matrix.label}</option>)}
        </select>
      </label>}
      <label>Alasan kemungkinan
        <textarea required maxLength={10000} value={reasonLikelihood} onChange={(event) => setReasonLikelihood(event.target.value)} disabled={save.isPending} />
        {fieldError('alasan_kemungkinan') && <small role="alert">{fieldError('alasan_kemungkinan')}</small>}
      </label>
      <label>Alasan dampak
        <textarea required maxLength={10000} value={reasonImpact} onChange={(event) => setReasonImpact(event.target.value)} disabled={save.isPending} />
        {fieldError('alasan_dampak') && <small role="alert">{fieldError('alasan_dampak')}</small>}
      </label>
      <label>Kesimpulan
        <textarea required maxLength={10000} value={conclusion} onChange={(event) => setConclusion(event.target.value)} disabled={save.isPending} />
      </label>
      {stage === 'TARGET_SETELAH' && <label>Alasan perubahan tingkat
        <textarea maxLength={10000} value={levelReason} onChange={(event) => setLevelReason(event.target.value)} disabled={save.isPending} aria-describedby={`level-reason-help-${riskId}`} />
        <span id={`level-reason-help-${riskId}`} className="assessment-muted">Wajib diisi jika target berada pada tingkat yang lebih tinggi daripada risiko saat ini.</span>
      </label>}
      <button className="primary" type="submit" disabled={save.isPending || (profile ? !likelihood || !impact : !matrices.length || !matrixId)}>
        <Save size={15} />{save.isPending ? 'Menyimpan…' : 'Simpan penilaian'}
      </button>
      {stage === 'AKTUAL_SETELAH' && rating && <EvidenceAttach endpoint={`/api/penilaian/${rating.id}/bukti`} versions={finalVersions} refresh={refresh} />}
    </>}
  </form>
}

function AssessmentRiskMap({ riskMap, risks }: { riskMap: RiskMap; risks: Risk[] }) {
  const [stage, setStage] = useState<'SAAT_INI' | 'TARGET_SETELAH' | 'AKTUAL_SETELAH'>('SAAT_INI')
  const [selectedCell, setSelectedCell] = useState<string | null>(null)
  const likelihoods = riskMap.metode.kemungkinan.map((scale) => ({ nilai: scale.nilai, nama: scaleName(scale), kriteria: scale.kriteria }))
  const impacts = riskMap.metode.dampak.map((scale) => ({ nilai: scale.nilai, nama: scaleName(scale), kriteria: scale.kriteria }))
  const levels = riskMap.metode.tingkat.map((level) => ({ id: level.id, nama: level.nama, warna: level.warna }))
  const cells = riskMap.metode.matriks.map((cell) => ({
    kemungkinan: Number(riskMap.metode.kemungkinan.find((scale) => scale.id === cell.kemungkinan_id)?.nilai),
    dampak: Number(riskMap.metode.dampak.find((scale) => scale.id === cell.dampak_id)?.nilai),
    skor: cell.skor,
    tingkat_id: cell.tingkat_risiko_id,
  }))
  const stageMap = riskMap.peta[stage] ?? {}
  const matrixById = new Map(riskMap.metode.matriks.map((matrix) => [matrix.id, matrix]))
  const counts = Object.values(stageMap).reduce<Record<string, number>>((result, cell) => {
    const matrix = matrixById.get(cell.matriks_id)
    const likelihood = riskMap.metode.kemungkinan.find((scale) => scale.id === Number(matrix?.kemungkinan_id))?.nilai
    const impact = riskMap.metode.dampak.find((scale) => scale.id === Number(matrix?.dampak_id))?.nilai
    if (likelihood && impact) result[`${likelihood}-${impact}`] = cell.jumlah
    return result
  }, {})
  const ratingFor = (risk: Risk, selectedStage: string) => risk.ratings.find((rating) => rating.tahap === selectedStage)
  const rankedRisks = [...risks].sort((a, b) => {
    const aRating = ratingFor(a, stage)
    const bRating = ratingFor(b, stage)
    const aLevel = aRating?.tingkat?.batas_min_skor ?? -1
    const bLevel = bRating?.tingkat?.batas_min_skor ?? -1
    return bLevel - aLevel || (bRating?.matriks?.skor ?? -1) - (aRating?.matriks?.skor ?? -1) || a.kode.localeCompare(b.kode)
  })
  const visibleRisks = selectedCell
    ? rankedRisks.filter((risk) => {
      const rating = ratingFor(risk, stage)
      return rating?.kemungkinan && rating.dampak && `${rating.kemungkinan.nilai}-${rating.dampak.nilai}` === selectedCell
    })
    : rankedRisks
  const displayRating = (rating?: Rating) => rating
    ? `${rating.tingkat?.nama ?? 'Tingkat belum tersedia'} · skor ${rating.matriks?.skor ?? '—'}`
    : 'Belum dinilai'

  return <section className="ws-panel assessment-map">
    <header>
      <div><span className="ws-eyebrow">Evaluasi risiko</span><h2>Peta risiko · Matriks 5×5</h2></div>
      <p>Data skor dan tingkat mengikuti matriks metode yang tersimpan di backend.</p>
    </header>
    <MatriksGrid kemungkinan={likelihoods} dampak={impacts} tingkat={levels} cells={cells} counts={counts} onCellSelect={(key) => setSelectedCell((current) => current === key ? null : key)} readOnly />
    <div className="assessment-map-stages" role="group" aria-label="Pilih tahap penilaian">
      {([
        ['SAAT_INI', 'Saat ini'],
        ['TARGET_SETELAH', 'Target setelah RTP'],
        ['AKTUAL_SETELAH', 'Aktual setelah penanganan'],
      ] as const).map(([value, label]) => <button key={value} type="button" aria-pressed={stage === value} onClick={() => setStage(value)}>{label}</button>)}
    </div>
    {selectedCell && <p className="assessment-map-filter">Menampilkan risiko pada sel {selectedCell}. <button type="button" onClick={() => setSelectedCell(null)}>Hapus filter</button></p>}
    <div className="assessment-map-table-wrap">
      <table className="assessment-map-table">
        <caption>Risiko diurutkan dari tingkat dan skor tertinggi untuk tahap yang dipilih.</caption>
        <thead><tr><th scope="col">Risiko</th><th scope="col">Nilai tahap</th><th scope="col">Kriteria penerimaan</th><th scope="col">Perlakuan</th></tr></thead>
        <tbody>{visibleRisks.map((risk) => {
          const rating = ratingFor(risk, stage)
          const proposal = risk.proposals.find((item) => item.penilaian_id === risk.ratings.find((item) => item.tahap === 'SAAT_INI')?.id)
          const level = rating?.tingkat
          return <tr key={risk.id}>
            <th scope="row"><strong>{risk.kode}</strong><span>{risk.skenario}</span></th>
            <td>{displayRating(rating)}{level?.perlu_rtp && <span className="assessment-map-flag">RTP wajib</span>}{level?.perlu_persetujuan_pemilik && <span className="assessment-map-flag">Persetujuan pemilik risiko</span>}</td>
            <td>{level?.kriteria_penerimaan ?? '—'}</td>
            <td>{proposal ? treatmentName(proposal.keputusan) : 'Belum diusulkan'}</td>
          </tr>
        })}</tbody>
      </table>
    </div>
    <section className="assessment-map-comparison" aria-labelledby="assessment-comparison-title">
      <h3 id="assessment-comparison-title">Perbandingan perubahan tingkat</h3>
      {risks.map((risk) => {
        const current = ratingFor(risk, 'SAAT_INI')
        const target = ratingFor(risk, 'TARGET_SETELAH')
        const actual = ratingFor(risk, 'AKTUAL_SETELAH')
        return <article key={risk.id}>
          <strong>{risk.kode} · {risk.skenario}</strong>
          <span>Saat ini: {displayRating(current)}</span>
          <span>Target RTP: {displayRating(target)}</span>
          <span>Aktual: {displayRating(actual)}</span>
          {target?.alasan_perubahan_level && <p>Alasan perubahan target: {target.alasan_perubahan_level}</p>}
        </article>
      })}
    </section>
  </section>
}

function treatmentName(value: string): string {
  return ({
    MITIGASI: 'Modify · Mitigasi (RTP)',
    TERIMA: 'Retain · Terima',
    HINDARI: 'Avoid · Hindari',
    ALIHKAN: 'Share · Bagikan',
  } as Record<string, string>)[value] ?? value
}

export function AsesmenDetailPage({ assessmentId }: { assessmentId: number }) {
  const session = useSession()
  const queryClient = useQueryClient()
  const [riskForm, setRiskForm] = useState<RiskForm>(emptyRisk)
  const [riskErrors, setRiskErrors] = useState<Record<string, string[]>>({})
  const [editingRisk, setEditingRisk] = useState<number | null>(null)
  const [activeTab, setActiveTab] = useState('informasi')
  const [assessmentFormState, setAssessmentFormState] = useState<{ id: number; tujuan: string; ruang_lingkup: string } | null>(null)
  const [planForm, setPlanForm] = useState({ pic_id: '', uraian: '', target_selesai: '', risiko_ids: [] as string[] })
  const refresh = () => queryClient.invalidateQueries({ queryKey: ['asesmen-detail', assessmentId] })
  const detail = useQuery({
    queryKey: ['asesmen-detail', assessmentId],
    queryFn: () => loadDetail(assessmentId),
    enabled: Number.isSafeInteger(assessmentId) && assessmentId > 0,
  })
  const assessment = detail.data?.assessment
  const assessmentForm = assessment && assessmentFormState?.id === assessment.id
    ? assessmentFormState
    : { id: assessment?.id ?? 0, tujuan: assessment?.tujuan ?? '', ruang_lingkup: assessment?.ruang_lingkup ?? '' }
  const references = useQuery({
    queryKey: ['asesmen-detail-options', assessment?.versi_metode_id, session.data?.roleCodes?.includes('STAFF')],
    enabled: Boolean(assessment) && !session.isPending,
    queryFn: async ({ signal }) => {
      const [threats, vulnerabilities, impacts, employees, matrixPage, likelihoods, impactScales, levels, assets, finalVersions] = await Promise.all([
        getOptions('/api/ancaman', byKodeNama, signal),
        getOptions('/api/kerentanan', byKodeNama, signal),
        getOptions('/api/area-dampak', byKodeNama, signal),
        getOptions('/api/pegawai', byKodeNama, signal),
        getPage('/api/matriks-risiko', { per_page: 100, 'filter[versi_metode_id]': assessment?.versi_metode_id }, signal),
        getPage('/api/skala-kemungkinan', { per_page: 100, 'filter[versi_metode_id]': assessment?.versi_metode_id }, signal),
        getPage('/api/skala-dampak', { per_page: 100, 'filter[versi_metode_id]': assessment?.versi_metode_id }, signal),
        getPage('/api/tingkat-risiko', { per_page: 100, 'filter[versi_metode_id]': assessment?.versi_metode_id }, signal),
        getPage('/api/aset', { per_page: 100 }, signal),
        session.data?.roleCodes?.includes('STAFF')
          ? getPage('/api/dokumen-versi', { per_page: 100, 'filter[status]': 'FINAL' }, signal)
          : Promise.resolve({ data: [] as Row[] }),
      ])
      const likelihoodNames = new Map(likelihoods.data.map((row) => [row.id, String(row.label ?? row.nama ?? row.nilai ?? row.id)]))
      const impactNames = new Map(impactScales.data.map((row) => [row.id, String(row.label ?? row.nama ?? row.nilai ?? row.id)]))
      const levelNames = new Map(levels.data.map((row) => [row.id, String(row.nama ?? row.kode ?? row.id)]))
      const matrices = optionRows(matrixPage.data, { likelihood: likelihoodNames, impact: impactNames, level: levelNames })
      return {
        threats,
        vulnerabilities,
        impacts,
        employees,
        matrices,
        finalVersions: finalVersions.data.map((row) => ({
          id: row.id,
          label: `Dokumen #${row.dokumen_id} · versi ${String(row.nomor_versi ?? row.id)}`,
        })),
        assets: assets.data.map((row) => ({ id: row.id, label: `${String(row.kode || `#${row.id}`)} · ${String(row.nama || 'Aset')}` })),
      }
    },
  })
  const isAuthor = Boolean(assessment && session.data && assessment.dibuat_oleh === session.data.id && session.data.roleCodes?.includes('STAFF'))
  const canWrite = Boolean(assessment && isAuthor && assessment.status === 'DRAFT')
  const canHandle = Boolean(assessment && isAuthor && assessment.status === 'DIREVIU')
  const canReview = Boolean(session.data?.roleCodes?.some((role) => ['KASUB', 'KABID', 'KASATKER'].includes(role)))
  const assessmentSave = useMutation({
    mutationFn: () => api.patch(`/api/asesmen/${assessmentId}`, { tujuan: assessmentForm.tujuan, ruang_lingkup: assessmentForm.ruang_lingkup }),
    onSuccess: () => { toast.success('Informasi asesmen tersimpan.'); refresh() },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const [snapshotAssetId, setSnapshotAssetId] = useState('')
  const [snapshotCriticality, setSnapshotCriticality] = useState('1')
  const [snapshotRationale, setSnapshotRationale] = useState('')
  const snapshotSave = useMutation({
    mutationFn: () => api.post(`/api/asesmen/${assessmentId}/aset`, {
      aset_id: Number(snapshotAssetId),
      nilai_kekritisan: Number(snapshotCriticality),
      dasar_kekritisan: snapshotRationale,
    }),
    onSuccess: () => {
      toast.success('Snapshot aset ditambahkan ke asesmen.')
      setSnapshotAssetId('')
      setSnapshotCriticality('1')
      setSnapshotRationale('')
      refresh()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const backPath = canWrite ? '/buat-asesmen' : canReview ? '/review-asesmen' : '/monitoring'
  const riskSave = useMutation({
    mutationFn: async (values: { id: number | null; form: RiskForm }) => {
      const payload = {
        ...values.form,
        ancaman_id: Number(values.form.ancaman_id),
        kerentanan_id: Number(values.form.kerentanan_id),
        area_dampak_id: Number(values.form.area_dampak_id),
        pemilik_risiko_id: Number(values.form.pemilik_risiko_id),
        asesmen_aset_ids: values.form.asesmen_aset_ids.map(Number),
      }
      if (values.id) return api.patch(`/api/risiko/${values.id}`, payload)
      return api.post(`/api/asesmen/${assessmentId}/risiko`, payload)
    },
    onSuccess: () => {
      setRiskErrors({})
      toast.success(editingRisk ? 'Risiko diperbarui.' : 'Risiko ditambahkan.')
      setRiskForm(emptyRisk)
      setEditingRisk(null)
      refresh()
    },
    onError: (error) => { setRiskErrors(fieldErrors(error)); toast.error(errorMessage(error)) },
  })
  const submit = useMutation({
    mutationFn: () => api.post(`/api/asesmen/${assessmentId}/ajukan`),
    onSuccess: () => { toast.success('Asesmen diajukan ke tahap review berikutnya.'); refresh() },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const submitResidual = useMutation({
    mutationFn: () => api.post(`/api/asesmen/${assessmentId}/ajukan-residual`),
    onSuccess: () => { toast.success('Hasil penanganan diajukan untuk review akhir.'); refresh() },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const planSave = useMutation({
    mutationFn: () => api.post(`/api/asesmen/${assessmentId}/rencana-penanganan`, {
      pic_id: Number(planForm.pic_id || session.data?.pegawai?.id),
      uraian: planForm.uraian,
      target_selesai: planForm.target_selesai,
      risiko_ids: planForm.risiko_ids.map(Number),
    }),
    onSuccess: () => {
      toast.success('Rencana Tindak Pengendalian tersimpan.')
      setPlanForm({ pic_id: '', uraian: '', target_selesai: '', risiko_ids: [] })
      refresh()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
  const setField = (field: keyof RiskForm, value: string) => setRiskForm((current) => ({ ...current, [field]: value }))
  const riskFieldError = (field: string) => riskErrors[field]?.[0]
  const edit = (risk: Risk) => {
    setEditingRisk(risk.id)
    setRiskForm({
      ancaman_id: String(risk.ancaman_id),
      kerentanan_id: String(risk.kerentanan_id),
      area_dampak_id: String(risk.area_dampak_id),
      pemilik_risiko_id: String(risk.pemilik_risiko_id),
      skenario: risk.skenario,
      uraian_kerentanan: risk.uraian_kerentanan,
      uraian_dampak: risk.uraian_dampak,
      arah_dampak: risk.arah_dampak,
      konteks: risk.konteks ?? '',
      konsekuensi_kerahasiaan: risk.konsekuensi_kerahasiaan ?? '',
      konsekuensi_integritas: risk.konsekuensi_integritas ?? '',
      konsekuensi_ketersediaan: risk.konsekuensi_ketersediaan ?? '',
      konsekuensi_kepatuhan: risk.konsekuensi_kepatuhan ?? '',
      konsekuensi_operasional: risk.konsekuensi_operasional ?? '',
      asesmen_aset_ids: risk.asesmen_aset_ids?.map(String) ?? [],
    })
  }

  if (detail.isPending) return <p className="assessment-loading" role="status">Memuat asesmen…</p>
  if (detail.isError || !assessment) return <section className="ws-panel assessment-error" role="alert">
    <ShieldAlert size={25} /><h1>Asesmen tidak dapat dimuat</h1><p>{errorMessage(detail.error)}</p><Link to="/monitoring">Kembali</Link>
  </section>
  const tabs: { key: string; label: string; complete: boolean }[] = [
    { key: 'informasi', label: 'Informasi', complete: Boolean(assessment.tujuan && assessment.ruang_lingkup) },
    { key: 'aset', label: 'Aset', complete: detail.data.riskMap.aset.length > 0 },
    { key: 'risiko', label: 'Risiko & penilaian', complete: detail.data.risks.length > 0 },
    { key: 'kontrol', label: 'Kontrol', complete: false },
    { key: 'rtp', label: assessment.status === 'DIREVIU' ? 'Penanganan' : 'RTP', complete: !detail.data.risks.some((risk) => risk.proposals.some((proposal) => ['MODIFIKASI', 'MITIGASI'].includes(proposal.keputusan))) || detail.data.plans.length > 0 },
    { key: 'ringkasan', label: 'Peta & ringkasan', complete: detail.data.risks.length > 0 },
  ]
  const modifiableRisks = detail.data.risks.filter((risk) => risk.proposals.some((proposal) => ['MODIFIKASI', 'MITIGASI'].includes(proposal.keputusan)))

  return <div className="assessment-detail">
    <header className="assessment-detail-hero">
      <div>
        <Link className="assessment-back" to={backPath}><ArrowLeft size={15} /> Kembali</Link>
        <span className="ws-eyebrow">Risk Assessment · Versi {assessment.nomor_versi}</span>
        <h1>Asesmen #{assessment.id}</h1>
        <p>{statusNames[assessment.status] ?? assessment.status} · Unit #{assessment.unit_id} · Periode #{assessment.periode_id}</p>
      </div>
      <span className={`ws-badge ${assessment.status === 'DRAFT' ? 'neutral' : 'success'}`}><span />{statusNames[assessment.status] ?? assessment.status}</span>
    </header>

    <section className="ws-panel assessment-summary">
      <div><span>Unit · Periode · Metode</span><p>#{assessment.unit_id} · #{assessment.periode_id} · {detail.data.riskMap.metode.nama} versi {detail.data.riskMap.metode.versi}</p></div>
      <div><span>Status</span><p>{statusNames[assessment.status] ?? assessment.status}</p></div>
    </section>

    <nav className="assessment-tabs" role="tablist" aria-label="Bagian formulir asesmen">
      {tabs.map(({ key, label, complete }, index) => <button key={key} type="button" role="tab" id={`assessment-tab-${key}`} aria-controls={`assessment-panel-${key}`} aria-selected={activeTab === key} onClick={() => setActiveTab(key)} onKeyDown={(event) => {
        const nextIndex = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : -1
        if (nextIndex >= 0) {
          event.preventDefault()
          const nextKey = tabs[nextIndex].key
          event.currentTarget.parentElement?.querySelector<HTMLButtonElement>(`#assessment-tab-${nextKey}`)?.focus()
          setActiveTab(nextKey)
        }
      }}>
        <span>{label}</span><small>{complete ? 'Lengkap' : 'Belum lengkap'}</small>
      </button>)}
    </nav>

    <section id="assessment-panel-informasi" role="tabpanel" aria-labelledby="assessment-tab-informasi" hidden={activeTab !== 'informasi'} className="ws-panel assessment-info-panel">
      <header><div><span className="ws-eyebrow">Bagian 1</span><h2>Informasi asesmen</h2></div></header>
      {canWrite
        ? <form onSubmit={(event) => { event.preventDefault(); assessmentSave.mutate() }}>
          <TextInput label="Tujuan asesmen" value={assessmentForm.tujuan} onChange={(tujuan) => setAssessmentFormState({ ...assessmentForm, id: assessment.id, tujuan })} required disabled={assessmentSave.isPending} />
          <TextArea label="Ruang lingkup" value={assessmentForm.ruang_lingkup} onChange={(ruang_lingkup) => setAssessmentFormState({ ...assessmentForm, id: assessment.id, ruang_lingkup })} disabled={assessmentSave.isPending} />
          <button className="primary" type="submit" disabled={assessmentSave.isPending}>{assessmentSave.isPending ? 'Menyimpan…' : 'Simpan informasi'}</button>
        </form>
        : <div className="assessment-summary"><div><span>Tujuan</span><p>{assessment.tujuan}</p></div><div><span>Ruang lingkup</span><p>{assessment.ruang_lingkup}</p></div></div>}
    </section>

    <section id="assessment-panel-aset" role="tabpanel" aria-labelledby="assessment-tab-aset" hidden={activeTab !== 'aset'} className="ws-panel assessment-assets">
      <header><div><span className="ws-eyebrow">Pendekatan berbasis aset</span><h2>Snapshot aset</h2></div></header>
      {detail.data.riskMap.aset.length > 0
        ? <ul>{detail.data.riskMap.aset.map((asset) => <li key={asset.id}><strong>{asset.nama_snapshot}</strong><span>Nilai kekritisan {asset.nilai_kekritisan} · {asset.dasar_kekritisan}</span></li>)}</ul>
        : <p className="assessment-muted">Belum ada aset yang direkam pada asesmen ini.</p>}
      {canWrite && references.data && <form className="assessment-snapshot-form" onSubmit={(event) => { event.preventDefault(); snapshotSave.mutate() }}>
        <label>Aset
          <select required value={snapshotAssetId} onChange={(event) => setSnapshotAssetId(event.target.value)} disabled={snapshotSave.isPending}>
            <option value="">Pilih aset untuk direkam</option>
            {references.data.assets.map((asset) => <option key={asset.id} value={asset.id}>{asset.label}</option>)}
          </select>
        </label>
        <label>Nilai kekritisan<input type="number" min="0" max="99999999999.9999" step="any" required value={snapshotCriticality} onChange={(event) => setSnapshotCriticality(event.target.value)} disabled={snapshotSave.isPending} /></label>
        <TextInput label="Dasar kekritisan" value={snapshotRationale} onChange={setSnapshotRationale} required disabled={snapshotSave.isPending} />
        <button className="primary" type="submit" disabled={snapshotSave.isPending || !snapshotAssetId}>Rekam snapshot aset</button>
      </form>}
    </section>

    <div id="assessment-panel-risiko" role="tabpanel" aria-labelledby="assessment-tab-risiko" hidden={activeTab !== 'risiko'}>
    {detail.data.risks.map((risk) => {
      const initialRating = risk.ratings.find((rating) => rating.tahap === 'SAAT_INI')
      const proposal = risk.proposals.find((item) => item.penilaian_id === initialRating?.id)
      const targetRating = risk.ratings.find((rating) => rating.tahap === 'TARGET_SETELAH')
      return <section className="ws-panel assessment-risk" key={risk.id}>
        <header className="assessment-risk-heading">
          <div><span className="ws-eyebrow">Risiko {risk.kode}</span><h2>{risk.skenario}</h2></div>
          {canWrite && <button type="button" onClick={() => edit(risk)}>Ubah risiko</button>}
        </header>
        <div className="assessment-risk-meta">
          <p><strong>Ancaman:</strong> {references.data?.threats.find((item) => item.id === risk.ancaman_id)?.label ?? `#${risk.ancaman_id}`} · <strong>Kerentanan:</strong> {references.data?.vulnerabilities.find((item) => item.id === risk.kerentanan_id)?.label ?? `#${risk.kerentanan_id}`}</p>
          <p><strong>Area dampak:</strong> {references.data?.impacts.find((item) => item.id === risk.area_dampak_id)?.label ?? `#${risk.area_dampak_id}`} · <strong>Pemilik:</strong> {references.data?.employees.find((item) => item.id === risk.pemilik_risiko_id)?.label ?? `#${risk.pemilik_risiko_id}`}</p>
          <p><strong>Uraian kerentanan:</strong> {risk.uraian_kerentanan}</p>
          <p><strong>Uraian dampak:</strong> {risk.uraian_dampak}</p>
          <p><strong>Arah dampak:</strong> {risk.arah_dampak === 'P' ? 'Positif' : 'Negatif'}</p>
          {risk.konteks && <p><strong>Konteks:</strong> {risk.konteks}</p>}
          {risk.konsekuensi_kerahasiaan && <p><strong>Konsekuensi kerahasiaan:</strong> {risk.konsekuensi_kerahasiaan}</p>}
          {risk.konsekuensi_integritas && <p><strong>Konsekuensi integritas:</strong> {risk.konsekuensi_integritas}</p>}
          {risk.konsekuensi_ketersediaan && <p><strong>Konsekuensi ketersediaan:</strong> {risk.konsekuensi_ketersediaan}</p>}
          {risk.konsekuensi_kepatuhan && <p><strong>Konsekuensi kepatuhan:</strong> {risk.konsekuensi_kepatuhan}</p>}
          {risk.konsekuensi_operasional && <p><strong>Konsekuensi operasional:</strong> {risk.konsekuensi_operasional}</p>}
        </div>
        <div className="assessment-rating-grid">
          <RatingEditor key={`current-${initialRating?.updated_at ?? initialRating?.matriks_id ?? 'new'}`} riskId={risk.id} stage="SAAT_INI" rating={initialRating} matrices={references.data?.matrices ?? []} matrixRows={detail.data.riskMap.metode.matriks} likelihoods={detail.data.riskMap.metode.kemungkinan} impacts={detail.data.riskMap.metode.dampak} levels={detail.data.riskMap.metode.tingkat} profile={detail.data.riskMap.metode.profil_penilaian} editable={canWrite} refresh={refresh} />
          <RatingEditor key={`target-${targetRating?.updated_at ?? targetRating?.matriks_id ?? 'new'}`} riskId={risk.id} stage="TARGET_SETELAH" rating={targetRating} matrices={references.data?.matrices ?? []} matrixRows={detail.data.riskMap.metode.matriks} likelihoods={detail.data.riskMap.metode.kemungkinan} impacts={detail.data.riskMap.metode.dampak} levels={detail.data.riskMap.metode.tingkat} profile={detail.data.riskMap.metode.profil_penilaian} editable={canWrite} refresh={refresh} />
        </div>
        <section className="assessment-proposal">
          <h4>Usulan keputusan</h4>
          {proposal ? <p>{treatmentName(proposal.keputusan)} · {proposal.alasan}</p> : <p className="assessment-muted">Belum ada usulan.</p>}
          {canWrite && (initialRating
            ? <ProposalEditor riskId={risk.id} proposal={proposal} refresh={refresh} />
            : <p className="assessment-muted">Simpan penilaian saat ini sebelum mengusulkan keputusan.</p>)}
        </section>
      </section>
    })}

    {references.isError && <p className="assessment-alert" role="alert">Pilihan risiko/penilaian gagal dimuat: {errorMessage(references.error)}</p>}
    {detail.data.reviews.length > 0 && <section className="ws-panel assessment-review-history">
      <h2>Riwayat review</h2>
      {detail.data.reviews.map((review) => <article key={review.id}>
        <div><strong>{review.tahap} · {review.keputusan}</strong><span>{review.waktu}</span></div>
        <p>Reviewer #{review.reviewer_id}{review.catatan ? ` · ${review.catatan}` : ''}</p>
      </article>)}
    </section>}
    {canWrite && <section className="ws-panel assessment-risk-form">
      <header><div><span className="ws-eyebrow">Data awal</span><h2>{editingRisk ? 'Ubah risiko' : 'Tambah risiko'}</h2></div><FilePlus2 size={21} /></header>
      {references.isPending ? <p role="status">Memuat pilihan formulir…</p> : references.data && <form onSubmit={(event) => { event.preventDefault(); riskSave.mutate({ id: editingRisk, form: riskForm }) }}>
        <div className="assessment-form-grid">
          <SelectInput label="Ancaman" value={riskForm.ancaman_id} options={references.data.threats} onChange={(value) => setField('ancaman_id', value)} disabled={riskSave.isPending} error={riskFieldError('ancaman_id')} />
          <SelectInput label="Kerentanan" value={riskForm.kerentanan_id} options={references.data.vulnerabilities} onChange={(value) => setField('kerentanan_id', value)} disabled={riskSave.isPending} error={riskFieldError('kerentanan_id')} />
          <SelectInput label="Area dampak" value={riskForm.area_dampak_id} options={references.data.impacts} onChange={(value) => setField('area_dampak_id', value)} disabled={riskSave.isPending} error={riskFieldError('area_dampak_id')} />
          <SelectInput label="Pemilik risiko" value={riskForm.pemilik_risiko_id} options={references.data.employees} onChange={(value) => setField('pemilik_risiko_id', value)} disabled={riskSave.isPending} error={riskFieldError('pemilik_risiko_id')} />
          <label>Arah dampak<select value={riskForm.arah_dampak} onChange={(event) => setField('arah_dampak', event.target.value)} disabled={riskSave.isPending}><option value="N">Negatif</option><option value="P">Positif</option></select></label>
          <TextInput wide label="Skenario" value={riskForm.skenario} onChange={(value) => setField('skenario', value)} required disabled={riskSave.isPending} error={riskFieldError('skenario')} />
          <TextArea label="Uraian kerentanan" value={riskForm.uraian_kerentanan} onChange={(value) => setField('uraian_kerentanan', value)} disabled={riskSave.isPending} error={riskFieldError('uraian_kerentanan')} />
          <TextArea label="Uraian dampak" value={riskForm.uraian_dampak} onChange={(value) => setField('uraian_dampak', value)} disabled={riskSave.isPending} error={riskFieldError('uraian_dampak')} />
          <fieldset className="assessment-asset-links assessment-wide">
            <legend>Aset terkait</legend>
            {detail.data.riskMap.metode.profil_penilaian && <p className="assessment-muted">Metode profil baru memerlukan minimal satu aset untuk setiap risiko.</p>}
            {detail.data.riskMap.aset.length === 0
              ? <p className="assessment-muted">Rekam snapshot aset sebelum menautkannya ke risiko.</p>
              : detail.data.riskMap.aset.map((asset) => <label key={asset.id}><input type="checkbox" checked={riskForm.asesmen_aset_ids.includes(String(asset.id))} onChange={(event) => setRiskForm((current) => ({ ...current, asesmen_aset_ids: event.target.checked ? [...current.asesmen_aset_ids, String(asset.id)] : current.asesmen_aset_ids.filter((id) => id !== String(asset.id)) }))} disabled={riskSave.isPending} />{asset.nama_snapshot}</label>)}
          </fieldset>
          {detail.data.riskMap.metode.profil_penilaian && <>
            <label>Konteks risiko<select required value={riskForm.konteks} onChange={(event) => setField('konteks', event.target.value)} disabled={riskSave.isPending}>
              <option value="">Pilih konteks</option><option value="STRATEGIS">Strategis</option><option value="OPERASIONAL">Operasional</option><option value="PELAPORAN">Pelaporan</option><option value="KEPATUHAN">Kepatuhan</option>
            </select>{riskFieldError('konteks') && <small role="alert">{riskFieldError('konteks')}</small>}</label>
            <TextArea label="Konsekuensi kerahasiaan" value={riskForm.konsekuensi_kerahasiaan} onChange={(value) => setField('konsekuensi_kerahasiaan', value)} disabled={riskSave.isPending} error={riskFieldError('konsekuensi_kerahasiaan')} />
            <TextArea label="Konsekuensi integritas" value={riskForm.konsekuensi_integritas} onChange={(value) => setField('konsekuensi_integritas', value)} disabled={riskSave.isPending} error={riskFieldError('konsekuensi_integritas')} />
            <TextArea label="Konsekuensi ketersediaan" value={riskForm.konsekuensi_ketersediaan} onChange={(value) => setField('konsekuensi_ketersediaan', value)} disabled={riskSave.isPending} error={riskFieldError('konsekuensi_ketersediaan')} />
            <TextArea label="Konsekuensi kepatuhan" value={riskForm.konsekuensi_kepatuhan} onChange={(value) => setField('konsekuensi_kepatuhan', value)} disabled={riskSave.isPending} error={riskFieldError('konsekuensi_kepatuhan')} />
            <TextArea label="Konsekuensi operasional" value={riskForm.konsekuensi_operasional} onChange={(value) => setField('konsekuensi_operasional', value)} disabled={riskSave.isPending} error={riskFieldError('konsekuensi_operasional')} />
          </>}
          {riskFieldError('asesmen_aset_ids') && <small className="assessment-wide" role="alert">{riskFieldError('asesmen_aset_ids')}</small>}
        </div>
        <footer>
          {editingRisk && <button type="button" disabled={riskSave.isPending} onClick={() => { setEditingRisk(null); setRiskForm(emptyRisk) }}>Batal ubah</button>}
          <button type="submit" className="primary" disabled={riskSave.isPending || references.isError || (detail.data.riskMap.metode.profil_penilaian && riskForm.asesmen_aset_ids.length === 0)}>
            <Save size={15} />{riskSave.isPending ? 'Menyimpan…' : editingRisk ? 'Simpan perubahan' : 'Simpan risiko'}
          </button>
        </footer>
      </form>}
    </section>}
    </div>

    <section id="assessment-panel-kontrol" role="tabpanel" aria-labelledby="assessment-tab-kontrol" hidden={activeTab !== 'kontrol'} className="ws-panel assessment-info-panel">
      <span className="ws-eyebrow">Bagian 5</span><h2>Kontrol yang sudah ada</h2>
      <p className="assessment-muted">API untuk menautkan kontrol standar, kontrol internal, dan versi SOP pada risiko belum tersedia di backend. Bagian ini tetap baca-saja agar tidak menyimpan data yang belum didukung.</p>
    </section>

    <section id="assessment-panel-rtp" role="tabpanel" aria-labelledby="assessment-tab-rtp" hidden={activeTab !== 'rtp'} className="assessment-rtp-panel">
      {assessment.status === 'DIREVIU'
        ? <section className="ws-panel assessment-info-panel"><span className="ws-eyebrow">Bagian penanganan</span><h2>Penilaian aktual setelah penanganan</h2>
            <div className="assessment-plan-list">
              {detail.data.plans.length
                ? detail.data.plans.map((plan) => <HandlingPlanCard key={plan.id} plan={plan} canReport={Boolean(session.data?.roleCodes?.includes('STAFF') && session.data.pegawai?.id === plan.pic_id)} versions={references.data?.finalVersions ?? []} refresh={refresh} />)
                : <p className="assessment-muted">Belum ada rencana penanganan yang disetujui.</p>}
            </div>
            {detail.data.risks.map((risk) => {
              const actualRating = risk.ratings.find((rating) => rating.tahap === 'AKTUAL_SETELAH')
              return <RatingEditor key={`actual-${risk.id}-${actualRating?.matriks_id ?? 'new'}`} riskId={risk.id} stage="AKTUAL_SETELAH" rating={actualRating} matrices={references.data?.matrices ?? []} matrixRows={detail.data!.riskMap.metode.matriks} likelihoods={detail.data!.riskMap.metode.kemungkinan} impacts={detail.data!.riskMap.metode.dampak} levels={detail.data!.riskMap.metode.tingkat} profile={detail.data!.riskMap.metode.profil_penilaian} editable={canHandle} refresh={refresh} finalVersions={references.data?.finalVersions ?? []} />
            })}
            {canHandle && <button type="button" className="primary" onClick={() => submitResidual.mutate()} disabled={submitResidual.isPending}>{submitResidual.isPending ? 'Mengajukan…' : 'Ajukan hasil penanganan'}</button>}
          </section>
          : <section className="ws-panel assessment-info-panel"><span className="ws-eyebrow">Bagian 6–8</span><h2>Rencana Tindak Pengendalian (RTP)</h2>
            {detail.data.plans.map((plan) => <article className="assessment-plan-card" key={plan.id}><strong>{plan.kode} · {plan.status}</strong><p>{plan.uraian}</p><span>Target selesai {plan.target_selesai} · PIC #{plan.pic_id}</span></article>)}
            {!modifiableRisks.length && <p className="assessment-muted">Belum ada usulan MODIFIKASI yang memerlukan RTP.</p>}
            {canWrite && <form className="assessment-plan-form" onSubmit={(event) => { event.preventDefault(); planSave.mutate() }}>
              <label>PIC rencana
                <select required value={planForm.pic_id || String(session.data?.pegawai?.id ?? '')} onChange={(event) => setPlanForm((current) => ({ ...current, pic_id: event.target.value }))} disabled={planSave.isPending}>
                  <option value="">Pilih PIC</option>{(references.data?.employees ?? []).map((employee) => <option key={employee.id} value={employee.id}>{employee.label}</option>)}
                </select>
              </label>
              <TextArea label="Uraian RTP" value={planForm.uraian} onChange={(uraian) => setPlanForm((current) => ({ ...current, uraian }))} disabled={planSave.isPending} />
              <label>Target selesai<input type="date" required min={new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Jakarta' }).format(new Date())} value={planForm.target_selesai} onChange={(event) => setPlanForm((current) => ({ ...current, target_selesai: event.target.value }))} disabled={planSave.isPending} /></label>
              <fieldset className="assessment-asset-links">
                <legend>Risiko yang ditangani</legend>
                {modifiableRisks.map((risk) => <label key={risk.id}><input type="checkbox" checked={planForm.risiko_ids.includes(String(risk.id))} onChange={(event) => setPlanForm((current) => ({ ...current, risiko_ids: event.target.checked ? [...current.risiko_ids, String(risk.id)] : current.risiko_ids.filter((id) => id !== String(risk.id)) }))} disabled={planSave.isPending} />{risk.kode} · {risk.skenario}</label>)}
              </fieldset>
              <button type="submit" className="primary" disabled={planSave.isPending || !planForm.uraian.trim() || !planForm.target_selesai || !planForm.risiko_ids.length}>{planSave.isPending ? 'Menyimpan…' : 'Simpan RTP'}</button>
            </form>}
          </section>}
    </section>

    <section id="assessment-panel-ringkasan" role="tabpanel" aria-labelledby="assessment-tab-ringkasan" hidden={activeTab !== 'ringkasan'}>
      <AssessmentRiskMap riskMap={detail.data.riskMap} risks={detail.data.risks} />
      {detail.data.reviews.length > 0 && <section className="ws-panel assessment-review-history">
        <h2>Riwayat review</h2>
        {detail.data.reviews.map((review) => <article key={review.id}>
          <div><strong>{review.tahap} · {review.keputusan}</strong><span>{review.waktu}</span></div>
          <p>Reviewer #{review.reviewer_id}{review.catatan ? ` · ${review.catatan}` : ''}</p>
        </article>)}
      </section>}
      {canWrite && <footer className="assessment-submit">
        <span>Pengajuan akan memeriksa kelengkapan di server. Kekurangan ditampilkan sebagai validasi.</span>
        <button type="button" className="primary" onClick={() => submit.mutate()} disabled={submit.isPending || !detail.data.risks.length}>
          <Send size={16} />{submit.isPending ? 'Mengajukan…' : 'Ajukan untuk review'}
        </button>
      </footer>}
    </section>

    {!canWrite && detail.data.risks.length === 0 && <p className="assessment-muted">Asesmen ini dalam mode baca.</p>}
    <div className="assessment-audit-hint"><ClipboardCheck size={16} /> Keputusan dan kelengkapan akhir tetap divalidasi oleh backend sesuai role dan status workflow.</div>
  </div>
}

export function ProposalEditor({ riskId, proposal, refresh }: { riskId: number; proposal?: Proposal; refresh: () => void }) {
  const [decision, setDecision] = useState(proposal?.keputusan === 'MITIGASI' ? 'MODIFIKASI' : proposal?.keputusan ?? 'MODIFIKASI')
  const [reason, setReason] = useState(proposal?.alasan ?? '')
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const save = useMutation({
    mutationFn: () => api.post(`/api/risiko/${riskId}/usulan-keputusan`, { keputusan: decision, alasan: reason }),
    onSuccess: () => { setErrors({}); toast.success('Usulan keputusan tersimpan.'); refresh() },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) },
  })
  return <form className="assessment-proposal-form" onSubmit={(event) => { event.preventDefault(); save.mutate() }}>
    <label>Opsi perlakuan
      <select value={decision} onChange={(event) => setDecision(event.target.value)} disabled={save.isPending}>
        <option value="MODIFIKASI">Modifikasi / Mitigasi</option><option value="TERIMA">Terima</option>
      </select>
    </label>
    <label>Alasan / ringkasan rencana penanganan (RTP)
      <textarea required maxLength={10000} value={reason} onChange={(event) => setReason(event.target.value)} disabled={save.isPending} />
      {errors.alasan?.[0] && <small role="alert">{errors.alasan[0]}</small>}
    </label>
    {errors.keputusan?.[0] && <p role="alert">{errors.keputusan[0]}</p>}
    <button className="primary" type="submit" disabled={save.isPending}>{save.isPending ? 'Menyimpan…' : 'Simpan usulan'}</button>
  </form>
}

function EvidenceAttach({ endpoint, versions, refresh }: { endpoint: string; versions: Option[]; refresh: () => void }) {
  const [versionId, setVersionId] = useState('')
  const [description, setDescription] = useState('')
  const save = useMutation({
    mutationFn: () => api.post(endpoint, { dokumen_versi_id: Number(versionId), keterangan: description }),
    onSuccess: () => { toast.success('Bukti berhasil ditautkan.'); setVersionId(''); setDescription(''); refresh() },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return <div className="assessment-evidence">
    <label>Versi dokumen FINAL
      <select value={versionId} onChange={(event) => setVersionId(event.target.value)} disabled={save.isPending || versions.length === 0}>
        <option value="">Pilih bukti dokumen</option>{versions.map((version) => <option key={version.id} value={version.id}>{version.label}</option>)}
      </select>
    </label>
    <label>Keterangan bukti<input value={description} maxLength={10000} onChange={(event) => setDescription(event.target.value)} disabled={save.isPending} /></label>
    <button type="button" disabled={save.isPending || !versionId || !description.trim()} onClick={() => save.mutate()}>
      {save.isPending ? 'Menautkan…' : 'Tautkan bukti'}
    </button>
    {!versions.length && <small role="status">Belum ada versi dokumen FINAL dalam lingkup Anda.</small>}
  </div>
}

function HandlingPlanCard({ plan, canReport, versions, refresh }: { plan: HandlingPlan; canReport: boolean; versions: Option[]; refresh: () => void }) {
  const [description, setDescription] = useState('')
  const [status, setStatus] = useState('BERJALAN')
  const queryClient = useQueryClient()
  const progress = useQuery({
    queryKey: ['handling-progress', plan.id],
    queryFn: async ({ signal }) => (await getPage(`/api/rencana-penanganan/${plan.id}/progres`, { per_page: 100 }, signal)).data as HandlingProgress[],
  })
  const report = useMutation({
    mutationFn: () => api.post(`/api/rencana-penanganan/${plan.id}/progres`, { uraian: description, status }),
    onSuccess: async () => {
      toast.success('Progres penanganan tersimpan.')
      setDescription('')
      await queryClient.invalidateQueries({ queryKey: ['handling-progress', plan.id] })
      refresh()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })
  return <article className="assessment-plan-card">
    <header><div><strong>{plan.kode}</strong><span>{plan.status} · target {plan.target_selesai}</span></div></header>
    <p>{plan.uraian}</p>
    {progress.isPending ? <p role="status">Memuat progres…</p>
      : progress.isError ? <p role="alert">{errorMessage(progress.error)}</p>
        : progress.data?.length ? progress.data.map((item) => <div className="assessment-progress" key={item.id}>
          <strong>{item.status} · {item.dilaporkan_pada}</strong><p>{item.uraian}</p>
          {canReport && <EvidenceAttach endpoint={`/api/progres/${item.id}/bukti`} versions={versions} refresh={refresh} />}
        </div>)
          : <p className="assessment-muted">Belum ada progres penanganan.</p>}
    {canReport && <form className="assessment-progress-form" onSubmit={(event) => { event.preventDefault(); report.mutate() }}>
      <label>Progres<textarea required maxLength={10000} value={description} onChange={(event) => setDescription(event.target.value)} disabled={report.isPending} /></label>
      <label>Status<select value={status} onChange={(event) => setStatus(event.target.value)} disabled={report.isPending}><option value="BERJALAN">Berjalan</option><option value="SELESAI">Selesai</option></select></label>
      <button type="submit" className="primary" disabled={report.isPending || !description.trim()}>{report.isPending ? 'Menyimpan…' : 'Catat progres'}</button>
    </form>}
  </article>
}

function TextInput({ label, value, onChange, required = false, disabled = false, wide = false, maxLength = 10000, error }: { label: string; value: string; onChange: (value: string) => void; required?: boolean; disabled?: boolean; wide?: boolean; maxLength?: number; error?: string }) {
  return <label className={wide ? 'assessment-wide' : undefined}>{label}<input required={required} maxLength={maxLength} value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled} aria-invalid={Boolean(error)} />{error && <small role="alert">{error}</small>}</label>
}

function TextArea({ label, value, onChange, disabled = false, error }: { label: string; value: string; onChange: (value: string) => void; disabled?: boolean; error?: string }) {
  return <label>{label}<textarea required maxLength={10000} value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled} aria-invalid={Boolean(error)} />{error && <small role="alert">{error}</small>}</label>
}

function SelectInput({ label, value, options, onChange, disabled, error }: { label: string; value: string; options: Option[]; onChange: (value: string) => void; disabled: boolean; error?: string }) {
  return <label>{label}<select required value={value} onChange={(event) => onChange(event.target.value)} disabled={disabled}>
    <option value="">Pilih {label.toLowerCase()}</option>{options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
  </select>{error && <small role="alert">{error}</small>}</label>
}
