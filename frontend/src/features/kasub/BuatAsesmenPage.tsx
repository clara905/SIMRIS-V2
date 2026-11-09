import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { FilePlus2, ShieldCheck } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { useSession } from '../../api/auth'
import { MatriksGrid } from '../../components/MatriksGrid'
import { getPage, type Option } from '../satker/api'
import './BuatAsesmenPage.css'

type Form = { periode_id: string; tujuan: string; ruang_lingkup: string }
type CatalogRow = Record<string, unknown> & { id: number }
type RiskScale = { id: number; nilai: number; label: string; kriteria: string }
type RiskLevel = { id: number; kode: string; nama: string; warna: string; batas_min_skor: number | null; batas_max_skor: number | null }
type MethodOption = Option & { profil_penilaian: boolean }
const blank: Form = { periode_id: '', tujuan: '', ruang_lingkup: '' }

async function options(
  endpoint: string,
  label: (row: CatalogRow) => string,
  filter: (row: CatalogRow) => boolean = () => true,
  signal?: AbortSignal,
): Promise<Option[]> {
  const page = await getPage(endpoint, { per_page: 100 }, signal)
  return page.data.filter(filter).map((row) => ({ id: row.id, label: label(row) }))
}

function Options({ label, id, value, options, pending, onChange, error }: { label: string; id: string; value: string; options: Option[]; pending: boolean; onChange: (value: string) => void; error?: string }) {
  return <label className="kasub-create-field" htmlFor={id}><span>{label}</span><select id={id} value={value} disabled={pending} onChange={(event) => onChange(event.target.value)} required><option value="">Pilih {label.toLowerCase()}</option>{options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}</select>{error && <small role="alert">{error}</small>}{!pending && !options.length && <small role="status">Belum ada {label.toLowerCase()} yang tersedia.</small>}</label>
}

export function BuatAsesmenPage() {
  const navigate = useNavigate()
  const session = useSession()
  const [form, setForm] = useState<Form>(blank)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Jakarta' }).format(new Date())
  const period = useQuery({
    queryKey: ['kasub', 'create', 'period'],
    queryFn: ({ signal }) => options(
      '/api/periode-penilaian',
      (row) => `${String(row.nama || `#${row.id}`)} (${String(row.tanggal_mulai ?? '').slice(0, 10)} – ${String(row.tanggal_selesai ?? '').slice(0, 10)})`,
      (row) => String(row.tanggal_mulai ?? '').slice(0, 10) <= today && String(row.tanggal_selesai ?? '').slice(0, 10) >= today,
      signal,
    ),
  })
  const method = useQuery({
    queryKey: ['kasub', 'create', 'method'],
    queryFn: async ({ signal }): Promise<MethodOption[]> => {
      const page = await getPage('/api/versi-metode-risiko', { per_page: 100 }, signal)
      return page.data
        .filter((row) => row.dibekukan === true)
        .map((row) => ({
          id: row.id,
          label: `${String(row.nama || `Metode #${row.id}`)} · versi ${String(row.versi ?? '—')}`,
          profil_penilaian: Boolean(row.profil_penilaian),
        }))
        .sort((left, right) => right.id - left.id)
    },
  })
  const selectedMethod = method.data?.[0]
  const methodMatrix = useQuery({
    queryKey: ['kasub', 'create', 'method-matrix', selectedMethod?.id],
    enabled: Boolean(selectedMethod),
    queryFn: async ({ signal }) => {
      const filter = { 'filter[versi_metode_id]': selectedMethod!.id, per_page: 100 }
      const [likelihoods, impacts, levels, cells] = await Promise.all([
        getPage('/api/skala-kemungkinan', filter, signal),
        getPage('/api/skala-dampak', filter, signal),
        getPage('/api/tingkat-risiko', filter, signal),
        getPage('/api/matriks-risiko', filter, signal),
      ])
      return {
        kemungkinan: likelihoods.data as unknown as RiskScale[],
        dampak: impacts.data as unknown as RiskScale[],
        tingkat: levels.data as unknown as RiskLevel[],
        matriks: cells.data,
      }
    },
  })
  const save = useMutation({
    mutationFn: async () => (await api.post<{ data: { id: number } }>('/api/asesmen', {
      periode_id: Number(form.periode_id),
      tujuan: form.tujuan,
      ruang_lingkup: form.ruang_lingkup,
    })).data.data,
    onSuccess: (assessment) => { toast.success('Draft Form Risk Assessment berhasil dibuat.'); navigate(`/asesmen/${assessment.id}`) },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) },
  })
  const set = (field: keyof Form) => (value: string) => setForm((current) => ({ ...current, [field]: value }))
  const first = (field: string) => errors[field]?.[0]
  const loading = session.isPending || period.isPending || method.isPending
  const matrix = methodMatrix.data
  const matrixPairs = new Set((matrix?.matriks ?? []).map((cell) => `${cell.kemungkinan_id}:${cell.dampak_id}`))
  const isCompleteMatrix = Boolean(matrix && matrix.kemungkinan.length === 5 && matrix.dampak.length === 5 && matrix.matriks.length === 25 && matrixPairs.size === 25)
  const matrixCells = (matrix?.matriks ?? []).map((cell) => ({
    kemungkinan: matrix?.kemungkinan.find((scale) => scale.id === Number(cell.kemungkinan_id))?.nilai ?? 0,
    dampak: matrix?.dampak.find((scale) => scale.id === Number(cell.dampak_id))?.nilai ?? 0,
    skor: Number(cell.skor),
    tingkat_id: Number(cell.tingkat_risiko_id),
  }))

  return <div className="kasub-create-page">
    <header className="kasub-create-hero"><div><span className="ws-eyebrow">Ruang kerja STAFF</span><h1>Buat asesmen risiko</h1><p>Unit dan versi metode ditetapkan otomatis oleh server berdasarkan penempatan dan metode yang sudah dibekukan.</p></div><ShieldCheck size={28} aria-hidden="true" /></header>
    <form className="ws-panel kasub-create-form" onSubmit={(event) => { event.preventDefault(); setErrors({}); save.mutate() }} noValidate>
      <div className="kasub-create-form-heading"><FilePlus2 size={21} /><div><h2>Informasi asesmen</h2><p>Isi tujuan dan ruang lingkup. Penilaian risiko dilanjutkan pada detail draft.</p></div></div>
      {period.isError || method.isError ? <p role="alert" className="kasub-create-alert">Referensi formulir belum dapat dimuat. {errorMessage(period.error || method.error)}</p> : <div className="kasub-create-grid">
        <div className="kasub-create-field kasub-create-wide"><span>Unit kerja</span><p className="kasub-create-static">Ditentukan otomatis dari penempatan utama STAFF Anda.</p><small>Unit tidak dapat dipilih atau dikirim oleh klien.</small></div>
        <div className="kasub-create-field"><Options label="Periode asesmen" id="kasub-period" value={form.periode_id} options={period.data ?? []} pending={loading || save.isPending} onChange={set('periode_id')} error={first('periode_id')} /><small>Periode adalah rentang waktu pelaksanaan asesmen, bukan skala penilaian. Nilai kemungkinan dan dampak dinilai terpisah memakai matriks.</small></div>
        <div className="kasub-create-field"><span>Metode risiko</span><p className="kasub-create-static">{selectedMethod ? 'Matriks 5x5' : 'Belum ada metode yang dibekukan'}</p><small>Versi metode terbaru yang dibekukan dipilih otomatis oleh server; metode tidak dapat dipilih oleh penulis.</small>{method.data?.length === 0 && !method.isPending && <small role="alert">Metode risiko belum disiapkan Super Admin.</small>}{methodMatrix.isPending && <small role="status">Memuat matriks metode…</small>}{methodMatrix.isError && <small role="alert">{errorMessage(methodMatrix.error)}</small>}</div>
        {matrix && <section className="kasub-create-matrix kasub-create-wide" aria-labelledby="kasub-method-matrix-title">
          <div><h3 id="kasub-method-matrix-title">Pratinjau matriks 5×5</h3><p>Matriks aktual dari versi metode beku yang akan dipilih server; nilai sel bersumber dari API metode.</p></div>
          {isCompleteMatrix
            ? <MatriksGrid
                kemungkinan={matrix.kemungkinan.map((scale) => ({ nilai: scale.nilai, nama: scale.label || `Skala ${scale.nilai}`, kriteria: scale.kriteria }))}
                dampak={matrix.dampak.map((scale) => ({ nilai: scale.nilai, nama: scale.label || `Skala ${scale.nilai}`, kriteria: scale.kriteria }))}
                tingkat={matrix.tingkat.map((level) => ({ id: level.id, nama: level.nama, warna: level.warna }))}
                cells={matrixCells}
                readOnly
              />
            : <p className="kasub-create-alert" role="alert">Versi metode belum memiliki 5 skala kemungkinan, 5 skala dampak, dan 25 sel matriks. Hubungi Super Admin untuk menyiapkan metode beku yang lengkap.</p>}
        </section>}
        <label className="kasub-create-field kasub-create-wide" htmlFor="kasub-goal"><span>Tujuan asesmen</span><input id="kasub-goal" value={form.tujuan} maxLength={10000} disabled={loading || save.isPending} onChange={(event) => set('tujuan')(event.target.value)} required />{first('tujuan') && <small role="alert">{first('tujuan')}</small>}</label>
        <label className="kasub-create-field kasub-create-wide" htmlFor="kasub-scope"><span>Ruang lingkup</span><textarea id="kasub-scope" rows={5} value={form.ruang_lingkup} maxLength={10000} disabled={loading || save.isPending} onChange={(event) => set('ruang_lingkup')(event.target.value)} required />{first('ruang_lingkup') && <small role="alert">{first('ruang_lingkup')}</small>}</label>
      </div>}
      <footer><button type="button" disabled={save.isPending} onClick={() => navigate('/monitoring')}>Batal</button><button className="primary" type="submit" disabled={loading || save.isPending || period.isError || method.isError || !selectedMethod || !form.periode_id || !isCompleteMatrix || methodMatrix.isError}>{save.isPending ? 'Membuat draft…' : 'Buat draft asesmen'}</button></footer>
    </form>
  </div>
}
