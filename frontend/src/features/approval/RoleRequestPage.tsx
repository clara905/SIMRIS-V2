import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Check, Clock3, Send, ShieldCheck } from 'lucide-react'
import { api, errorMessage } from '../../api/client'
import { sessionKey } from '../../api/auth'
import styles from './RoleRequestPage.module.css'

type Submission = {
  id: number
  role_kode: string
  unit_id: number
  unit_nama?: string
  informasi_pendukung: string
  status: 'MENUNGGU' | 'DITOLAK' | 'DISETUJUI'
  alasan_penolakan: string | null
  created_at: string
}
type RoleStatus = { has_role: boolean; pengajuan: Submission | null }
type UnitOption = { id: number; nama: string; jenis: string; parent_id: number | null; kode?: string }
const roles = [
  { code: 'STAFF', label: 'Staf' },
  { code: 'KASUB', label: 'Kepala Subbidang' },
  { code: 'KABID', label: 'Kepala Bidang' },
  { code: 'KASATKER', label: 'Kepala Satker' },
]

export function RoleRequestPage() {
  const client = useQueryClient()
  const navigate = useNavigate()
  const status = useQuery({
    queryKey: ['pengajuan-role'],
    queryFn: async () => (await api.get<{ data: RoleStatus }>('/api/v1/pengajuan-role')).data.data,
    refetchInterval: 5_000,
  })
  const [role, setRole] = useState('STAFF')
  const units = useQuery({
    queryKey: ['pengajuan-role', 'unit-options', role],
    queryFn: async () => {
      const unitsResponse = await api.get<{ data: UnitOption[] }>(`/api/v1/unit-pilihan-role?role=${role}`)
      return unitsResponse.data.data
    },
  })
  const [unitId, setUnitId] = useState('')
  const [information, setInformation] = useState('')
  const [error, setError] = useState('')
  const [sending, setSending] = useState(false)
  const submission = status.data?.pengajuan
  const unitKind = role === 'KASATKER' ? 'SATKER' : role === 'KABID' ? 'BIDANG' : role === 'KASUB' ? 'SUBBIDANG' : null
  const eligibleUnits = (units.data ?? []).filter((unit) => unitKind ? unit.jenis === unitKind : false)
  const unitLabel = unitKind === 'SATKER' ? 'Satker' : unitKind === 'BIDANG' ? 'Bidang' : unitKind === 'SUBBIDANG' ? 'Subbidang' : 'Unit penempatan'

  useEffect(() => {
    if (!status.data?.has_role) return
    void client.invalidateQueries({ queryKey: sessionKey }).then(() => {
      navigate('/', { replace: true })
    })
  }, [client, navigate, status.data?.has_role])

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSending(true)
    setError('')
    try {
      await api.post('/api/v1/pengajuan-role', {
        role_kode: role,
        ...(role === 'STAFF' ? {} : { unit_id: Number(unitId) }),
        informasi_pendukung: information,
      })
      setInformation('')
      await client.invalidateQueries({ queryKey: ['pengajuan-role'] })
    } catch (reason) {
      setError(errorMessage(reason))
    } finally {
      setSending(false)
    }
  }

  return (
    <main className={styles.page}>
      <section className={styles.hero}>
        <span className={styles.eyebrow}><ShieldCheck size={15} /> AKSES SIMRIS</span>
        <h1>Ajukan role</h1>
        <p>Email Anda sudah terverifikasi. Lengkapi pengajuan supaya Admin Satker dapat meninjau akses SIMRIS Anda.</p>
      </section>

      {status.isPending ? <section className={styles.card}>Memuat status pengajuanÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦</section> : status.isError ? (
        <section className={styles.card} role="alert"><h2>Status belum dapat dimuat</h2><p>{errorMessage(status.error)}</p><button onClick={() => void status.refetch()}>Coba lagi</button></section>
      ) : submission?.status === 'MENUNGGU' ? (
        <section className={styles.card}>
          <div className={styles.status}><Clock3 size={20} /><div><h2>Pengajuan sedang ditinjau</h2><p>Admin Satker akan memeriksa permintaan role Anda.</p></div></div>
          <dl><dt>Role diajukan</dt><dd>{roles.find((item) => item.code === submission.role_kode)?.label ?? submission.role_kode}</dd><dt>{submission.role_kode === 'KASATKER' ? 'Satker' : submission.role_kode === 'KABID' ? 'Bidang' : submission.role_kode === 'KASUB' ? 'Subbidang' : 'Unit penempatan'}</dt><dd>{submission.unit_nama ?? `#${submission.unit_id}`}</dd><dt>Informasi pendukung</dt><dd>{submission.informasi_pendukung}</dd></dl>
        </section>
      ) : submission?.status === 'DISETUJUI' || status.data?.has_role ? (
        <section className={styles.card}><div className={styles.status}><Check size={20} /><div><h2>Role sudah diberikan</h2><p>Akses Anda sudah aktif. Membuka ruang kerjaÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦</p></div></div></section>
      ) : (
        <form className={styles.card} onSubmit={submit}>
          <h2>{submission?.status === 'DITOLAK' ? 'Perbarui pengajuan role' : 'Form pengajuan role'}</h2>
          {submission?.status === 'DITOLAK' && <div className={styles.rejection}><strong>Pengajuan sebelumnya ditolak</strong><p>{submission.alasan_penolakan || 'Silakan perbarui informasi pendukung sebelum mengajukan kembali.'}</p></div>}
          {role === 'STAFF' ? (
            <p>Unit penempatan akan mengikuti penempatan utama Anda.</p>
          ) : (
            <>
              <label htmlFor="role-request-unit">{unitLabel} yang akan ditempati</label>
              <select id="role-request-unit" value={unitId} onChange={(event) => setUnitId(event.target.value)} required disabled={sending || units.isPending}>
                <option value="">Pilih {unitLabel.toLowerCase()}</option>
                {eligibleUnits.map((unit) => <option key={unit.id} value={unit.id}>{unit.kode ? `${unit.kode} - ` : ''}{unit.nama}</option>)}
              </select>
            </>
          )}
          <label htmlFor="role-request-role">Role yang diajukan</label>
          <select id="role-request-role" value={role} onChange={(event) => { setRole(event.target.value); setUnitId('') }} disabled={sending}>{roles.map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}</select>
          <label htmlFor="role-request-info">Informasi pendukung</label>
          <textarea id="role-request-info" value={information} onChange={(event) => setInformation(event.target.value)} minLength={10} maxLength={3000} required rows={5} placeholder="Jelaskan tugas dan alasan Anda memerlukan role ini." disabled={sending} />
          {error && <p className={styles.error} role="alert">{error}</p>}
          <button type="submit" disabled={sending || (role !== 'STAFF' && !unitId) || information.trim().length < 10}><Send size={16} />{sending ? 'Mengirim...' : 'Kirim pengajuan'}</button>
        </form>
      )}
    </main>
  )
}
