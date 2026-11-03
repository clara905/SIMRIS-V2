import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Building2, Check, ClipboardCheck, Clock3, RefreshCw, ShieldCheck, UserRound, X } from 'lucide-react'
import { toast } from 'sonner'
import { api, errorMessage } from '../../api/client'
import styles from './ApprovalPage.module.css'

type Pending = { id: number; user_id: number; nama: string; nip: string; unit_nama: string; unit_id: number; role_kode: string; informasi_pendukung: string }
const roleOptions = [
  { value: 'STAFF', label: 'Staf' },
  { value: 'KASUB', label: 'Kepala Subbidang' },
  { value: 'KABID', label: 'Kepala Bidang' },
  { value: 'KASATKER', label: 'Kepala Satker' },
]

export function ApprovalPage() {
  const client = useQueryClient()
  const query = useQuery({
    queryKey: ['approval-akun'],
    queryFn: async () => (await api.get<{ data: Pending[] }>('/api/v1/approval-akun')).data.data,
    refetchInterval: 10_000,
  })
  const [rejecting, setRejecting] = useState<number | null>(null)
  const [note, setNote] = useState('')
  const [busyId, setBusyId] = useState<number | null>(null)

  const refresh = () => {
    void client.invalidateQueries({ queryKey: ['approval-akun'] })
    void client.invalidateQueries({ queryKey: ['approval-akun-count'] })
  }
  const decide = async (row: Pending, approve: boolean) => {
    setBusyId(row.id)
    try {
      if (approve) {
        await api.post(`/api/v1/approval-akun/${row.id}/setujui`)
        toast.success(`Pengajuan ${row.nama} berhasil disetujui.`)
      } else {
        if (!note.trim()) return
        await api.post(`/api/v1/approval-akun/${row.id}/tolak`, { alasan_penolakan: note.trim() })
        toast.success('Pengajuan role ditolak dan alasannya dikirim ke pengguna.')
        setRejecting(null)
        setNote('')
      }
      refresh()
    } catch (error) {
      const latest = await query.refetch()
      if (!latest.data?.some((application) => application.id === row.id)) {
        setRejecting(null)
        setNote('')
        toast.info('Pengajuan sudah diproses. Daftar telah diperbarui.')
      } else {
        toast.error(errorMessage(error))
      }
    } finally {
      setBusyId(null)
    }
  }

  return (
    <main className={styles.page}>
      <section className={styles.hero}>
        <div className={styles.heroCopy}>
          <span className={styles.eyebrow}><ShieldCheck size={15} /> PENGELOLAAN AKUN</span>
          <h1>Approval Akun</h1>
          <p>Tinjau pengajuan role dari pegawai di wilayah Satker Anda.</p>
        </div>
        <div className={styles.heroIcon} aria-hidden="true"><ClipboardCheck size={34} /></div>
      </section>

      <section className={styles.summary} aria-label="Ringkasan pendaftaran">
        <div className={styles.summaryIcon}><Clock3 size={19} /></div>
        <div><span>Menunggu persetujuan</span><strong>{query.data?.length ?? (query.isLoading ? '…' : '0')}</strong></div>
        <div className={styles.summaryAside}>Daftar diperbarui otomatis setiap 30 detik</div>
      </section>

      <div className={styles.listHeading}>
        <div><h2>Pengajuan role</h2><p>Periksa role yang diminta dan informasi pendukung sebelum memberi akses.</p></div>
        <button className={styles.refresh} onClick={refresh} disabled={query.isFetching} aria-label="Muat ulang daftar">
          <RefreshCw size={16} className={query.isFetching ? styles.spinning : undefined} />
          Muat ulang
        </button>
      </div>

      {query.isLoading ? (
        <div className={styles.cards} aria-label="Memuat pendaftaran">
          {[1, 2].map((item) => <div className={styles.skeleton} key={item}><div /><span /><span /><span /></div>)}
        </div>
      ) : query.error ? (
        <section className={styles.stateCard} role="alert">
          <div className={styles.stateIcon}><X size={20} /></div>
          <h2>Daftar belum dapat dimuat</h2>
          <p>{errorMessage(query.error)}</p>
          <button className={styles.primaryButton} onClick={() => void query.refetch()}>Coba lagi</button>
        </section>
      ) : query.data?.length ? (
        <div className={styles.cards}>
          {query.data.map((row) => (
            <article className={styles.card} key={row.id}>
              <div className={styles.cardTop}>
                <div className={styles.person}>
                  <div className={styles.avatar}><UserRound size={21} /></div>
                  <div><h3>{row.nama}</h3><span>NIP {row.nip}</span></div>
                </div>
                <span className={styles.pending}><i /> Menunggu</span>
              </div>
              <div className={styles.unit}><Building2 size={16} /><span>Penempatan</span><strong>{row.unit_nama}</strong></div>
              <div className={styles.unit}><ShieldCheck size={16} /><span>Role diajukan</span><strong>{roleOptions.find((role) => role.value === row.role_kode)?.label ?? row.role_kode}</strong></div>
              <p>{row.informasi_pendukung}</p>
              <div className={styles.cardBottom}>
                <div className={styles.actions}>
                  <button className={styles.rejectButton} onClick={() => { setRejecting(rejecting === row.id ? null : row.id); setNote('') }} disabled={busyId === row.id}>
                    <X size={16} /> Tolak
                  </button>
                  <button className={styles.primaryButton} onClick={() => void decide(row, true)} disabled={busyId === row.id}>
                    <Check size={16} /> {busyId === row.id ? 'Memproses…' : 'Setujui role'}
                  </button>
                </div>
              </div>
              {rejecting === row.id && (
                <div className={styles.rejectPanel}>
                  <label htmlFor={`reject-note-${row.id}`}>Alasan penolakan untuk pengguna</label>
                  <textarea id={`reject-note-${row.id}`} value={note} onChange={(event) => setNote(event.target.value)} placeholder="Tuliskan alasan penolakan untuk jejak audit…" rows={3} maxLength={1000} />
                  <div><span>{note.length}/1000</span><button className={styles.rejectConfirm} onClick={() => void decide(row, false)} disabled={!note.trim() || busyId === row.id}>{busyId === row.id ? 'Memproses…' : 'Konfirmasi penolakan'}</button></div>
                </div>
              )}
            </article>
          ))}
        </div>
      ) : (
        <section className={styles.stateCard}>
          <div className={styles.emptyIcon}><Check size={22} /></div>
          <h2>Semua sudah ditinjau</h2>
          <p>Belum ada pengajuan role yang menunggu persetujuan di wilayah Satker Anda.</p>
        </section>
      )}
    </main>
  )
}
