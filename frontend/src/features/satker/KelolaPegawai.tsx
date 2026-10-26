import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import { byKodeNama, dateOnly, getOptions, getPage, type Option, type Row } from './api'
import { Edit3, Plus, RefreshCw, UserRound } from 'lucide-react'

type EmployeeForm = { nip: string; nama: string; email_kontak: string; jenis_kepegawaian: string; aktif: boolean }
type PlacementForm = { unit_id: string; mulai: string; selesai: string; utama: boolean }
const blank: EmployeeForm = { nip: '', nama: '', email_kontak: '', jenis_kepegawaian: '', aktif: true }
const blankPlacement: PlacementForm = { unit_id: '', mulai: '', selesai: '', utama: true }
const stringOf = (value: unknown) => value == null ? '' : String(value)
const firstError = (errors: Record<string, string[]>, key: string) => errors[key]?.[0]

export function KelolaPegawai() {
  const client = useQueryClient()
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<Row | null>(null)
  const [creating, setCreating] = useState(false)
  const [deactivating, setDeactivating] = useState<Row | null>(null)
  const [form, setForm] = useState<EmployeeForm>(blank)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const units = useQuery({ queryKey: ['satker', 'pegawai', 'units'], queryFn: ({ signal }) => getOptions('/api/unit-organisasi', byKodeNama, signal) })
  const list = useQuery({ queryKey: ['satker', 'pegawai', page], queryFn: ({ signal }) => getPage('/api/pegawai', { page, per_page: 10, sort: '-id' }, signal) })
  const refresh = () => { void client.invalidateQueries({ queryKey: ['satker', 'pegawai'] }); void client.invalidateQueries({ queryKey: ['satker', 'opt', 'pegawai'] }) }
  const save = useMutation({
    mutationFn: async (): Promise<{ row: Row; created: boolean }> => {
      const body = { ...form, nip: form.nip.trim() || null, nama: form.nama.trim(), email_kontak: form.email_kontak.trim(), jenis_kepegawaian: form.jenis_kepegawaian.trim() }
      const response = selected
        ? await api.patch<{ data: Row }>('/api/pegawai/' + selected.id, body)
        : await api.post<{ data: Row }>('/api/pegawai', body)
      return { row: response.data.data, created: !selected }
    },
    onSuccess: ({ row, created }) => {
      toast.success(created ? 'Pegawai dibuat. Tambahkan penempatan aktif agar pegawai dapat dipilih saat membuat akun.' : 'Data pegawai diperbarui.')
      setSelected(row)
      setCreating(false)
      setErrors({})
      refresh()
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) },
  })
  const deactivate = useMutation({ mutationFn: async () => { if (deactivating) await api.delete('/api/pegawai/' + deactivating.id) }, onSuccess: () => { toast.success('Pegawai dinonaktifkan.'); setDeactivating(null); refresh() }, onError: (error) => toast.error(errorMessage(error)) })
  const submit = (event: FormEvent) => {
    event.preventDefault()
    const next: Record<string, string[]> = {}
    if (!form.nama.trim()) next.nama = ['Nama wajib diisi.']
    if (!form.email_kontak.trim()) next.email_kontak = ['Email kontak wajib diisi.']
    if (!form.jenis_kepegawaian.trim()) next.jenis_kepegawaian = ['Jenis kepegawaian wajib diisi.']
    if (form.nip && !/^\d{18}$/.test(form.nip)) next.nip = ['NIP harus 18 digit.']
    if (Object.keys(next).length) { setErrors(next); return }
    setErrors({}); save.mutate()
  }
  const startNew = () => { setSelected(null); setCreating(true); setForm(blank); setErrors({}) }
  const startEdit = (row: Row) => { setCreating(false); setSelected(row); setErrors({}); setForm({ nip: stringOf(row.nip), nama: stringOf(row.nama), email_kontak: stringOf(row.email_kontak), jenis_kepegawaian: stringOf(row.jenis_kepegawaian), aktif: row.aktif === true || row.aktif === 1 }) }
  const close = () => { setCreating(false); setSelected(null) }
  const data = list.data

  return <>
    <div className="ws-page-heading"><div><span className="ws-eyebrow">Pengelolaan satker</span><h1>Pegawai &amp; penempatan</h1><p>Kelola identitas pegawai dan riwayat penempatannya pada unit dalam wilayah satker.</p></div><button className="primary" onClick={startNew}><Plus size={16} /> Tambah pegawai</button></div>
    <section className="ws-panel"><div className="ws-register-tools"><strong>Daftar pegawai</strong><button className="ws-refresh" aria-label="Perbarui pegawai" disabled={list.isFetching} onClick={() => void list.refetch()}><RefreshCw size={16} /></button></div>
      {list.isPending ? <div className="ws-loading" role="status">Memuat pegawai...</div> : list.isError ? <div className="ws-empty" role="alert"><strong>Daftar pegawai belum dapat dimuat</strong><p>{errorMessage(list.error)}</p><button onClick={() => void list.refetch()}>Coba lagi</button></div> : !data?.data.length ? <div className="ws-empty"><UserRound size={28} /><strong>Belum ada pegawai</strong><p>Tambahkan pegawai untuk membuat penempatan di unit satker.</p></div> : <>
        <div className="ws-table-wrap"><table className="ws-table"><thead><tr><th>Nama</th><th>NIP</th><th>Email kontak</th><th>Jenis</th><th>Status</th><th>Aksi</th></tr></thead><tbody>{data.data.map((row) => { const active = row.aktif === true || row.aktif === 1; return <tr key={row.id}><td>{stringOf(row.nama)}</td><td>{stringOf(row.nip) || '-'}</td><td>{stringOf(row.email_kontak) || '-'}</td><td>{stringOf(row.jenis_kepegawaian) || '-'}</td><td><span className={'ws-badge ' + (active ? 'success' : 'neutral')}><span />{active ? 'AKTIF' : 'NONAKTIF'}</span></td><td><button className="ws-row-open" aria-label={'Ubah pegawai ' + row.nama} onClick={() => startEdit(row)}><Edit3 size={16} /></button>{active && <button className="ws-row-open" aria-label={'Nonaktifkan pegawai ' + row.nama} onClick={() => setDeactivating(row)}>×</button>}</td></tr> })}</tbody></table></div>
        <div className="ws-pagination"><span>{data.meta.from || 0}-{data.meta.to || 0} dari {data.meta.total} pegawai</span><div><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Sebelumnya</button><span>{data.meta.current_page} / {data.meta.last_page}</span><button disabled={page >= data.meta.last_page} onClick={() => setPage((value) => value + 1)}>Berikutnya</button></div></div>
      </>}
    </section>
    <Drawer open={creating || !!selected} onClose={close} title={creating ? 'Tambah pegawai' : 'Ubah pegawai'} description="Pegawai baru dapat langsung diberi penempatan aktif di wilayah satker.">
      <form className="ws-profile-form" onSubmit={submit} noValidate><div className="ws-profile-form-grid">
        <Input id="employee-nip" label="NIP (opsional)" value={form.nip} error={firstError(errors, 'nip')} disabled={save.isPending} onChange={(value) => setForm((old) => ({ ...old, nip: value }))} />
        <Input id="employee-name" label="Nama" value={form.nama} error={firstError(errors, 'nama')} disabled={save.isPending} onChange={(value) => setForm((old) => ({ ...old, nama: value }))} />
        <Input id="employee-email" label="Email kontak" type="email" value={form.email_kontak} error={firstError(errors, 'email_kontak')} disabled={save.isPending} onChange={(value) => setForm((old) => ({ ...old, email_kontak: value }))} />
        <Input id="employee-type" label="Jenis kepegawaian" value={form.jenis_kepegawaian} error={firstError(errors, 'jenis_kepegawaian')} disabled={save.isPending} onChange={(value) => setForm((old) => ({ ...old, jenis_kepegawaian: value }))} />
        {!creating && <label><input type="checkbox" checked={form.aktif} disabled={save.isPending} onChange={(event) => setForm((old) => ({ ...old, aktif: event.target.checked }))} /> Aktif</label>}
      </div><div className="ws-profile-actions"><button className="primary" disabled={save.isPending}>{save.isPending ? 'Menyimpan...' : 'Simpan'}</button></div></form>
      {selected && <PenempatanPegawai pegawai={selected} units={units.data ?? []} />}
    </Drawer>
    <ConfirmDialog open={!!deactivating} onClose={() => setDeactivating(null)} title="Nonaktifkan pegawai?" description={[`Pegawai ${stringOf(deactivating?.nama)} akan dinonaktifkan.`, 'Riwayat pegawai tetap tersimpan.']} confirmLabel="Nonaktifkan" pending={deactivate.isPending} onConfirm={() => deactivate.mutateAsync()} />
  </>
}

function Input({ id, label, value, error, type = 'text', disabled, onChange }: { id: string; label: string; value: string; error?: string; type?: string; disabled?: boolean; onChange: (value: string) => void }) {
  return <div className="ws-profile-field"><label htmlFor={id}>{label}</label><input id={id} type={type} value={value} disabled={disabled} onChange={(event) => onChange(event.target.value)} />{error && <small role="alert">{error}</small>}</div>
}

function PenempatanPegawai({ pegawai, units }: { pegawai: Row; units: Option[] }) {
  const client = useQueryClient()
  const [editing, setEditing] = useState<Row | null>(null)
  const [form, setForm] = useState<PlacementForm>(blankPlacement)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const list = useQuery({ queryKey: ['satker', 'pegawai', pegawai.id, 'penempatan'], queryFn: ({ signal }) => getPage('/api/penempatan-pegawai', { per_page: 100, sort: '-id', filter: { pegawai_id: pegawai.id } }, signal) })
  const save = useMutation({
    mutationFn: async () => {
      const body = { pegawai_id: pegawai.id, unit_id: Number(form.unit_id), mulai: form.mulai, selesai: form.selesai || null, utama: form.utama }
      if (editing) await api.patch('/api/penempatan-pegawai/' + editing.id, body)
      else await api.post('/api/penempatan-pegawai', body)
    },
    onSuccess: () => { toast.success(editing ? 'Penempatan diperbarui.' : 'Penempatan ditambahkan.'); setEditing(null); setForm(blankPlacement); setErrors({}); void client.invalidateQueries({ queryKey: ['satker', 'pegawai', pegawai.id, 'penempatan'] }); void client.invalidateQueries({ queryKey: ['satker', 'pegawai'] }) },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) },
  })
  const start = (row?: Row) => { setEditing(row ?? null); setErrors({}); setForm(row ? { unit_id: stringOf(row.unit_id), mulai: dateOnly(row.mulai), selesai: dateOnly(row.selesai), utama: row.utama === true || row.utama === 1 } : blankPlacement) }
  const submit = (event: FormEvent) => { event.preventDefault(); const missing: Record<string, string[]> = {}; if (!form.unit_id) missing.unit_id = ['Pilih unit.']; if (!form.mulai) missing.mulai = ['Tanggal mulai wajib diisi.']; if (Object.keys(missing).length) { setErrors(missing); return } setErrors({}); save.mutate() }
  return <section className="ws-panel"><div className="ws-register-tools"><h3>Penempatan</h3><button type="button" onClick={() => start()}><Plus size={15} /> Tambah penempatan</button></div>
    {list.isPending ? <p role="status">Memuat penempatan...</p> : list.isError ? <p role="alert">{errorMessage(list.error)}</p> : list.data.data.length === 0 ? <p>Belum ada riwayat penempatan.</p> : <div className="ws-table-wrap"><table className="ws-table"><thead><tr><th>Unit</th><th>Mulai</th><th>Selesai</th><th>Utama</th><th>Aksi</th></tr></thead><tbody>{list.data.data.map((row) => <tr key={row.id}><td>{units.find((unit) => unit.id === Number(row.unit_id))?.label ?? '#' + row.unit_id}</td><td>{dateOnly(row.mulai)}</td><td>{dateOnly(row.selesai) || '-'}</td><td>{row.utama ? 'Ya' : 'Tidak'}</td><td><button type="button" className="ws-row-open" aria-label={'Ubah penempatan ' + row.id} onClick={() => start(row)}><Edit3 size={15} /></button></td></tr>)}</tbody></table></div>}
    <form className="ws-profile-form" onSubmit={submit} noValidate><div className="ws-profile-form-grid"><div className="ws-profile-field"><label htmlFor="placement-unit">Unit</label><select id="placement-unit" value={form.unit_id} disabled={save.isPending} onChange={(event) => setForm((old) => ({ ...old, unit_id: event.target.value }))}><option value="">Pilih unit</option>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.label}</option>)}</select>{firstError(errors, 'unit_id') && <small role="alert">{firstError(errors, 'unit_id')}</small>}</div>
      <Input id="placement-start" label="Mulai" type="date" value={form.mulai} error={firstError(errors, 'mulai')} disabled={save.isPending} onChange={(value) => setForm((old) => ({ ...old, mulai: value }))} />
      <Input id="placement-end" label="Selesai (opsional)" type="date" value={form.selesai} error={firstError(errors, 'selesai')} disabled={save.isPending} onChange={(value) => setForm((old) => ({ ...old, selesai: value }))} />
      <label><input type="checkbox" checked={form.utama} disabled={save.isPending} onChange={(event) => setForm((old) => ({ ...old, utama: event.target.checked }))} /> Penempatan utama</label></div><div className="ws-profile-actions"><button disabled={save.isPending}>{save.isPending ? 'Menyimpan...' : editing ? 'Simpan penempatan' : 'Tambah penempatan'}</button>{editing && <button type="button" onClick={() => start()}>Batal ubah</button>}</div></form>
  </section>
}
