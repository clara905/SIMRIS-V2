import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { FilePlus2, ShieldCheck } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { byKodeNama, getOptions, type Option } from '../satker/api'
import './BuatAsesmenPage.css'

type Form = { unit_id: string; periode_id: string; versi_metode_id: string; tujuan: string; ruang_lingkup: string }
const blank: Form = { unit_id: '', periode_id: '', versi_metode_id: '', tujuan: '', ruang_lingkup: '' }

function Options({ label, id, value, options, pending, onChange, error }: { label: string; id: string; value: string; options: Option[]; pending: boolean; onChange: (value: string) => void; error?: string }) {
  return <label className="kasub-create-field" htmlFor={id}><span>{label}</span><select id={id} value={value} disabled={pending} onChange={(event) => onChange(event.target.value)} required><option value="">Pilih {label.toLowerCase()}</option>{options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}</select>{error && <small role="alert">{error}</small>}</label>
}

export function BuatAsesmenPage() {
  const navigate = useNavigate()
  const [form, setForm] = useState<Form>(blank)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const unit = useQuery({ queryKey: ['kasub', 'create', 'unit'], queryFn: ({ signal }) => getOptions('/api/unit-organisasi', (row) => String(row.nama || row.kode || `#${row.id}`), signal) })
  const period = useQuery({ queryKey: ['kasub', 'create', 'period'], queryFn: ({ signal }) => getOptions('/api/periode-penilaian', (row) => String(row.nama || `#${row.id}`), signal) })
  const method = useQuery({ queryKey: ['kasub', 'create', 'method'], queryFn: ({ signal }) => getOptions('/api/versi-metode-risiko', byKodeNama, signal) })
  const save = useMutation({
    mutationFn: async () => (await api.post<{ data: { id: number } }>('/api/asesmen', { ...form, unit_id: Number(form.unit_id), periode_id: Number(form.periode_id), versi_metode_id: Number(form.versi_metode_id) })).data.data,
    onSuccess: (assessment) => { toast.success('Draft Form Risk Assessment berhasil dibuat.'); navigate(`/monitoring?tab=asesmen&id=${assessment.id}`) },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) },
  })
  const set = (field: keyof Form) => (value: string) => setForm((current) => ({ ...current, [field]: value }))
  const first = (field: string) => errors[field]?.[0]
  const loading = unit.isPending || period.isPending || method.isPending

  return <div className="kasub-create-page">
    <header className="kasub-create-hero"><div><span className="ws-eyebrow">Kewenangan Kasub</span><h1>Buat Form Risk Assessment</h1><p>Buat draft hanya untuk sub bidang dalam scope role Kasub aktif Anda.</p></div><ShieldCheck size={28} aria-hidden="true" /></header>
    <form className="ws-panel kasub-create-form" onSubmit={(event) => { event.preventDefault(); setErrors({}); save.mutate() }} noValidate>
      <div className="kasub-create-form-heading"><FilePlus2 size={21} /><div><h2>Informasi asesmen</h2><p>Lengkapi risiko, penilaian, dan usulan keputusan sebelum mengajukan ke Kabid.</p></div></div>
      {unit.isError || period.isError || method.isError ? <p role="alert" className="kasub-create-alert">Referensi formulir belum dapat dimuat. {errorMessage(unit.error || period.error || method.error)}</p> : <div className="kasub-create-grid"><Options label="Sub bidang" id="kasub-unit" value={form.unit_id} options={unit.data ?? []} pending={loading || save.isPending} onChange={set('unit_id')} error={first('unit_id')} /><Options label="Periode penilaian" id="kasub-period" value={form.periode_id} options={period.data ?? []} pending={loading || save.isPending} onChange={set('periode_id')} error={first('periode_id')} /><Options label="Metode risiko" id="kasub-method" value={form.versi_metode_id} options={method.data ?? []} pending={loading || save.isPending} onChange={set('versi_metode_id')} error={first('versi_metode_id')} /><label className="kasub-create-field kasub-create-wide" htmlFor="kasub-goal"><span>Tujuan asesmen</span><input id="kasub-goal" value={form.tujuan} maxLength={10000} disabled={loading || save.isPending} onChange={(event) => set('tujuan')(event.target.value)} required />{first('tujuan') && <small role="alert">{first('tujuan')}</small>}</label><label className="kasub-create-field kasub-create-wide" htmlFor="kasub-scope"><span>Ruang lingkup</span><textarea id="kasub-scope" rows={5} value={form.ruang_lingkup} maxLength={10000} disabled={loading || save.isPending} onChange={(event) => set('ruang_lingkup')(event.target.value)} required />{first('ruang_lingkup') && <small role="alert">{first('ruang_lingkup')}</small>}</label></div>}
      <footer><button type="button" disabled={save.isPending} onClick={() => navigate('/review-asesmen')}>Batal</button><button className="primary" type="submit" disabled={loading || save.isPending || unit.isError || period.isError || method.isError}>{save.isPending ? 'Membuat draft…' : 'Buat draft asesmen'}</button></footer>
    </form>
  </div>
}
