import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import { byKodeNama, getOptions, getPage, type Option, type Row } from './api'
import { Edit3, Plus, RefreshCw, Send, Trash2 } from 'lucide-react'

const controlKinds = ['SOP', 'KEBIJAKAN', 'TEKNIS', 'FISIK', 'LAINNYA']
const str = (value: unknown) => value == null ? '' : String(value)
const first = (errors: Record<string, string[]>, key: string) => errors[key]?.[0]
type ControlForm = { unit_pemilik_id: string; kode: string; nama: string; jenis: string; deskripsi: string; aktif: boolean }
type SopForm = { unit_pemilik_id: string; kode: string; judul: string }
type VersionForm = { dokumen_versi_id: string; tanggal_berlaku: string }
const blankControl: ControlForm = { unit_pemilik_id: '', kode: '', nama: '', jenis: '', deskripsi: '', aktif: true }
const blankSop: SopForm = { unit_pemilik_id: '', kode: '', judul: '' }

export function KelolaKepatuhan() {
  const [tab, setTab] = useState<'control' | 'sop'>('control')
  return <>
    <div className="ws-page-heading"><div><span className="ws-eyebrow">Pengelolaan satker</span><h1>SOP &amp; kontrol internal</h1><p>Kelola kontrol internal, dokumen SOP, tautan, dan pengajuan untuk ditinjau.</p></div></div>
    <div className="ws-tabs" role="tablist" aria-label="Modul kepatuhan"><button role="tab" aria-selected={tab === 'control'} onClick={() => setTab('control')}>Kontrol internal</button><button role="tab" aria-selected={tab === 'sop'} onClick={() => setTab('sop')}>SOP</button></div>
    {tab === 'control' ? <KontrolInternal /> : <SopManagement />}
  </>
}

function KontrolInternal() {
  const client = useQueryClient()
  const [editing, setEditing] = useState<Row | null>(null)
  const [creating, setCreating] = useState(false)
  const [removing, setRemoving] = useState<Row | null>(null)
  const [form, setForm] = useState<ControlForm>(blankControl)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const list = useQuery({ queryKey: ['satker', 'kepatuhan', 'kontrol-internal'], queryFn: ({ signal }) => getPage('/api/kontrol-internal', { per_page: 100, sort: '-id' }, signal) })
  const units = useQuery({ queryKey: ['satker', 'kepatuhan', 'unit'], queryFn: ({ signal }) => getOptions('/api/unit-organisasi', byKodeNama, signal) })
  const controls = useQuery({ queryKey: ['satker', 'kepatuhan', 'kontrol-options'], queryFn: ({ signal }) => getOptions('/api/kontrol', byKodeNama, signal) })
  const sopVersions = useQuery({ queryKey: ['satker', 'kepatuhan', 'sop-approved'], queryFn: async ({ signal }) => (await getPage('/api/sop-versi', { per_page: 100, filter: { status: 'DISAHKAN' } }, signal)).data })
  const refresh = () => { void client.invalidateQueries({ queryKey: ['satker', 'kepatuhan'] }) }
  const save = useMutation({ mutationFn: async () => { const body = { kode: form.kode.trim(), nama: form.nama.trim(), jenis: form.jenis, deskripsi: form.deskripsi.trim(), aktif: form.aktif }; if (editing) await api.patch('/api/kontrol-internal/' + editing.id, body); else await api.post('/api/kontrol-internal', { ...body, unit_pemilik_id: Number(form.unit_pemilik_id) }) }, onSuccess: () => { toast.success(editing ? 'Kontrol internal diperbarui.' : 'Kontrol internal dibuat.'); setEditing(null); setCreating(false); setErrors({}); refresh() }, onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) } })
  const remove = useMutation({ mutationFn: async () => { if (removing) await api.delete('/api/kontrol-internal/' + removing.id) }, onSuccess: () => { toast.success('Kontrol internal dihapus.'); setRemoving(null); refresh() }, onError: (error) => toast.error(errorMessage(error)) })
  const openNew = () => { setEditing(null); setCreating(true); setErrors({}); setForm(blankControl) }
  const openEdit = (row: Row) => { setEditing(row); setCreating(false); setErrors({}); setForm({ unit_pemilik_id: str(row.unit_pemilik_id), kode: str(row.kode), nama: str(row.nama), jenis: str(row.jenis), deskripsi: str(row.deskripsi), aktif: row.aktif === true || row.aktif === 1 }) }
  const submit = (event: FormEvent) => { event.preventDefault(); const next: Record<string, string[]> = {}; for (const key of ['kode', 'nama', 'jenis'] as const) if (!form[key].trim()) next[key] = ['Kolom wajib diisi.']; if (!form.deskripsi.trim()) next.deskripsi = ['Deskripsi wajib diisi.']; if (creating && !form.unit_pemilik_id) next.unit_pemilik_id = ['Pilih unit.']; if (Object.keys(next).length) { setErrors(next); return } setErrors({}); save.mutate() }
  return <section className="ws-panel"><div className="ws-register-tools"><strong>Daftar kontrol internal</strong><button className="primary" onClick={openNew}><Plus size={16} /> Tambah kontrol</button><button className="ws-refresh" aria-label="Perbarui kontrol internal" disabled={list.isFetching} onClick={() => void list.refetch()}><RefreshCw size={16} /></button></div>
    {list.isPending ? <div className="ws-loading" role="status">Memuat kontrol...</div> : list.isError ? <div className="ws-empty" role="alert"><strong>Data belum dapat dimuat</strong><p>{errorMessage(list.error)}</p><button onClick={() => void list.refetch()}>Coba lagi</button></div> : !list.data.data.length ? <div className="ws-empty"><strong>Belum ada kontrol internal</strong><p>Tambahkan kontrol untuk menghubungkannya dengan standar dan versi SOP.</p></div> : <div className="ws-table-wrap"><table className="ws-table"><thead><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Unit ID</th><th>Status</th><th>Aksi</th></tr></thead><tbody>{list.data.data.map((row) => <tr key={row.id}><td>{str(row.kode)}</td><td>{str(row.nama)}</td><td>{str(row.jenis)}</td><td>{str(row.unit_pemilik_id)}</td><td>{row.aktif ? 'Aktif' : 'Nonaktif'}</td><td><button className="ws-row-open" aria-label={'Ubah kontrol ' + row.kode} onClick={() => openEdit(row)}><Edit3 size={15} /></button><button className="ws-row-open" aria-label={'Hapus kontrol ' + row.kode} onClick={() => setRemoving(row)}><Trash2 size={15} /></button></td></tr>)}</tbody></table></div>}
    <Drawer open={creating || !!editing} onClose={() => { setCreating(false); setEditing(null) }} title={creating ? 'Tambah kontrol internal' : 'Ubah kontrol internal'} description="Unit pemilik tidak dapat diubah setelah kontrol dibuat."><form className="ws-profile-form" onSubmit={submit} noValidate><div className="ws-profile-form-grid">
      {creating && <Select id="control-unit" label="Unit pemilik" value={form.unit_pemilik_id} options={units.data ?? []} error={first(errors, 'unit_pemilik_id')} onChange={(value) => setForm((old) => ({ ...old, unit_pemilik_id: value }))} />}
      <Input id="control-code" label="Kode" value={form.kode} error={first(errors, 'kode')} onChange={(value) => setForm((old) => ({ ...old, kode: value }))} />
      <Input id="control-name" label="Nama" value={form.nama} error={first(errors, 'nama')} onChange={(value) => setForm((old) => ({ ...old, nama: value }))} />
      <div className="ws-profile-field"><label htmlFor="control-kind">Jenis</label><select id="control-kind" value={form.jenis} onChange={(event) => setForm((old) => ({ ...old, jenis: event.target.value }))}><option value="">Pilih jenis</option>{controlKinds.map((kind) => <option key={kind}>{kind}</option>)}</select>{first(errors, 'jenis') && <small role="alert">{first(errors, 'jenis')}</small>}</div>
      <div className="ws-profile-field"><label htmlFor="control-description">Deskripsi</label><textarea id="control-description" rows={3} value={form.deskripsi} onChange={(event) => setForm((old) => ({ ...old, deskripsi: event.target.value }))} />{first(errors, 'deskripsi') && <small role="alert">{first(errors, 'deskripsi')}</small>}</div>
      <label><input type="checkbox" checked={form.aktif} onChange={(event) => setForm((old) => ({ ...old, aktif: event.target.checked }))} /> Aktif</label>
    </div><div className="ws-profile-actions"><button className="primary" disabled={save.isPending}>{save.isPending ? 'Menyimpan...' : 'Simpan'}</button></div></form>{editing && <ControlLinks control={editing} controls={controls.data ?? []} sopVersions={sopVersions.data ?? []} />}</Drawer>
    <ConfirmDialog open={!!removing} onClose={() => setRemoving(null)} title="Hapus kontrol internal?" description={[`Kontrol ${str(removing?.kode)} akan dihapus.`, 'Jika masih digunakan, backend akan menolak penghapusan.']} confirmLabel="Hapus" pending={remove.isPending} onConfirm={() => remove.mutateAsync()} />
  </section>
}

function ControlLinks({ control, controls, sopVersions }: { control: Row; controls: Option[]; sopVersions: Row[] }) {
  const [standard, setStandard] = useState('')
  const [sopVersion, setSopVersion] = useState('')
  const client = useQueryClient()
  const standards = useQuery({ queryKey: ['satker', 'kepatuhan', 'control', control.id, 'standar'], queryFn: ({ signal }) => getPage('/api/kontrol-internal/' + control.id + '/standar', { per_page: 100 }, signal) })
  const sops = useQuery({ queryKey: ['satker', 'kepatuhan', 'control', control.id, 'sop'], queryFn: ({ signal }) => getPage('/api/kontrol-internal/' + control.id + '/sop', { per_page: 100 }, signal) })
  const link = useMutation({ mutationFn: async ({ relation, value }: { relation: 'standar' | 'sop'; value: string }) => api.post('/api/kontrol-internal/' + control.id + '/' + relation, relation === 'standar' ? { kontrol_id: Number(value) } : { sop_versi_id: Number(value) }), onSuccess: () => { toast.success('Tautan berhasil ditambahkan.'); setStandard(''); setSopVersion(''); void client.invalidateQueries({ queryKey: ['satker', 'kepatuhan', 'control', control.id] }) }, onError: (error) => toast.error(errorMessage(error)) })
  return <section className="ws-panel"><h3>Tautan standar &amp; SOP</h3><div className="ws-profile-form-grid"><div className="ws-profile-field"><label htmlFor="control-standard">Kontrol standar</label><select id="control-standard" value={standard} onChange={(event) => setStandard(event.target.value)}><option value="">Pilih kontrol standar</option>{controls.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select><button type="button" disabled={!standard || link.isPending} onClick={() => link.mutate({ relation: 'standar', value: standard })}>Hubungkan</button></div><div className="ws-profile-field"><label htmlFor="control-sop-version">Versi SOP disahkan</label><select id="control-sop-version" value={sopVersion} onChange={(event) => setSopVersion(event.target.value)}><option value="">Pilih versi SOP</option>{sopVersions.map((row) => <option key={row.id} value={row.id}>SOP #{str(row.sop_id)} · v{str(row.nomor_versi)}</option>)}</select><button type="button" disabled={!sopVersion || link.isPending} onClick={() => link.mutate({ relation: 'sop', value: sopVersion })}>Hubungkan</button></div></div><p>Standar tertaut: {standards.isError ? errorMessage(standards.error) : standards.data?.meta.total ?? '...'}</p><p>Versi SOP tertaut: {sops.isError ? errorMessage(sops.error) : sops.data?.meta.total ?? '...'}</p></section>
}

function SopManagement() {
  const client = useQueryClient()
  const [editing, setEditing] = useState<Row | null>(null)
  const [selected, setSelected] = useState<Row | null>(null)
  const [creating, setCreating] = useState(false)
  const [removing, setRemoving] = useState<Row | null>(null)
  const [submitting, setSubmitting] = useState<Row | null>(null)
  const [form, setForm] = useState<SopForm>(blankSop)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const list = useQuery({ queryKey: ['satker', 'kepatuhan', 'sop'], queryFn: ({ signal }) => getPage('/api/sop', { per_page: 100, sort: '-id' }, signal) })
  const units = useQuery({ queryKey: ['satker', 'kepatuhan', 'unit'], queryFn: ({ signal }) => getOptions('/api/unit-organisasi', byKodeNama, signal) })
  const save = useMutation({ mutationFn: async () => { if (editing) await api.patch('/api/sop/' + editing.id, { kode: form.kode.trim(), judul: form.judul.trim() }); else await api.post('/api/sop', { ...form, unit_pemilik_id: Number(form.unit_pemilik_id), kode: form.kode.trim(), judul: form.judul.trim() }) }, onSuccess: () => { toast.success(editing ? 'SOP diperbarui.' : 'SOP dibuat.'); setEditing(null); setCreating(false); setErrors({}); void client.invalidateQueries({ queryKey: ['satker', 'kepatuhan'] }) }, onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) } })
  const remove = useMutation({ mutationFn: async () => { if (removing) await api.delete('/api/sop/' + removing.id) }, onSuccess: () => { toast.success('SOP dihapus.'); setRemoving(null); void client.invalidateQueries({ queryKey: ['satker', 'kepatuhan', 'sop'] }) }, onError: (error) => toast.error(errorMessage(error)) })
  const submit = (event: FormEvent) => { event.preventDefault(); const next: Record<string, string[]> = {}; if (creating && !form.unit_pemilik_id) next.unit_pemilik_id = ['Pilih unit.']; if (!form.kode.trim()) next.kode = ['Kode wajib diisi.']; if (!form.judul.trim()) next.judul = ['Judul wajib diisi.']; if (Object.keys(next).length) { setErrors(next); return } setErrors({}); save.mutate() }
  const openCreate = () => { setEditing(null); setCreating(true); setErrors({}); setForm(blankSop) }
  const openEdit = (row: Row) => { setEditing(row); setCreating(false); setErrors({}); setForm({ unit_pemilik_id: str(row.unit_pemilik_id), kode: str(row.kode), judul: str(row.judul) }) }
  const finalizeSubmit = useMutation({ mutationFn: async (row: Row) => api.post('/api/sop-versi/' + row.id + '/ajukan'), onSuccess: () => { toast.success('Versi diajukan untuk ditinjau.'); setSubmitting(null); void client.invalidateQueries({ queryKey: ['satker', 'kepatuhan'] }) }, onError: (error) => toast.error(errorMessage(error)) })
  return <section className="ws-panel"><div className="ws-register-tools"><strong>Daftar SOP</strong><button className="primary" onClick={openCreate}><Plus size={16} /> Tambah SOP</button><button className="ws-refresh" aria-label="Perbarui SOP" disabled={list.isFetching} onClick={() => void list.refetch()}><RefreshCw size={16} /></button></div>
    {list.isPending ? <div className="ws-loading" role="status">Memuat SOP...</div> : list.isError ? <div className="ws-empty" role="alert"><strong>SOP belum dapat dimuat</strong><p>{errorMessage(list.error)}</p><button onClick={() => void list.refetch()}>Coba lagi</button></div> : !list.data.data.length ? <div className="ws-empty"><strong>Belum ada SOP</strong><p>Buat data SOP, lalu tambah versi dari dokumen SOP yang sudah FINAL.</p></div> : <div className="ws-table-wrap"><table className="ws-table"><thead><tr><th>Kode</th><th>Judul</th><th>Unit</th><th>Aksi</th></tr></thead><tbody>{list.data.data.map((row) => <tr key={row.id}><td>{str(row.kode)}</td><td>{str(row.judul)}</td><td>{str(row.unit_pemilik_id)}</td><td><button className="ws-row-open" aria-label={'Kelola versi SOP ' + row.kode} onClick={() => setSelected(row)}><Edit3 size={15} /></button><button className="ws-row-open" aria-label={'Ubah SOP ' + row.kode} onClick={() => openEdit(row)}><Edit3 size={15} /></button><button className="ws-row-open" aria-label={'Hapus SOP ' + row.kode} onClick={() => setRemoving(row)}><Trash2 size={15} /></button></td></tr>)}</tbody></table></div>}
    <Drawer open={creating || !!editing} onClose={() => { setCreating(false); setEditing(null) }} title={creating ? 'Tambah SOP' : 'Ubah SOP'} description="Unit pemilik tidak dapat diubah setelah SOP dibuat."><form className="ws-profile-form" onSubmit={submit} noValidate><div className="ws-profile-form-grid">{creating && <Select id="sop-unit" label="Unit pemilik" value={form.unit_pemilik_id} options={units.data ?? []} error={first(errors, 'unit_pemilik_id')} onChange={(value) => setForm((old) => ({ ...old, unit_pemilik_id: value }))} />}<Input id="sop-code" label="Kode" value={form.kode} error={first(errors, 'kode')} onChange={(value) => setForm((old) => ({ ...old, kode: value }))} /><Input id="sop-title" label="Judul" value={form.judul} error={first(errors, 'judul')} onChange={(value) => setForm((old) => ({ ...old, judul: value }))} /></div><div className="ws-profile-actions"><button className="primary" disabled={save.isPending}>{save.isPending ? 'Menyimpan...' : 'Simpan'}</button></div></form></Drawer>
    <Drawer open={!!selected} onClose={() => setSelected(null)} title={'Versi SOP ' + str(selected?.kode)} description="Hanya versi DRAFT yang dapat diubah. Pengajuan untuk ditinjau tidak dapat dibatalkan.">{selected && <SopVersions sop={selected} onSubmit={(row) => setSubmitting(row)} submitting={finalizeSubmit.isPending} />}</Drawer>
    <ConfirmDialog open={!!removing} onClose={() => setRemoving(null)} title="Hapus SOP draft?" description={[`SOP ${str(removing?.kode)} akan dihapus.`, 'Jika SOP sudah memiliki versi atau masih dirujuk, backend akan menolak penghapusan.']} confirmLabel="Hapus" pending={remove.isPending} onConfirm={() => remove.mutateAsync()} />
    <ConfirmDialog open={!!submitting} onClose={() => setSubmitting(null)} title="Ajukan versi SOP?" description={['Versi ini akan dikirim ke alur peninjauan.', 'Admin Satker tidak dapat mengubah atau mereview versi setelah diajukan.']} confirmLabel="Ajukan" pending={finalizeSubmit.isPending} onConfirm={async () => { if (submitting) await finalizeSubmit.mutateAsync(submitting) }} />
  </section>
}

function SopVersions({ sop, onSubmit, submitting }: { sop: Row; onSubmit: (row: Row) => void; submitting: boolean }) {
  const client = useQueryClient()
  const [selectedVersion, setSelectedVersion] = useState<Row | null>(null)
  const [documentId, setDocumentId] = useState('')
  const [form, setForm] = useState<VersionForm>({ dokumen_versi_id: '', tanggal_berlaku: '' })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [controlId, setControlId] = useState('')
  const docs = useQuery({ queryKey: ['satker', 'kepatuhan', 'sop-docs'], queryFn: ({ signal }) => getPage('/api/dokumen', { per_page: 100, filter: { jenis: 'SOP' } }, signal) })
  const docOptions = docs.data?.data.map((row) => ({ id: row.id, label: `${row.kode} — ${row.judul}` })) ?? []
  const versions = useQuery({ queryKey: ['satker', 'kepatuhan', 'sop-doc-versions', documentId], queryFn: ({ signal }) => getPage('/api/dokumen/' + documentId + '/versi', { per_page: 100 }, signal), enabled: !!documentId })
  const finalOptions = versions.data?.data.filter((row) => row.status === 'FINAL') ?? []
  const list = useQuery({ queryKey: ['satker', 'kepatuhan', 'sop', sop.id, 'versions'], queryFn: ({ signal }) => getPage('/api/sop/' + sop.id + '/versi', { per_page: 100, sort: '-id' }, signal) })
  const controls = useQuery({ queryKey: ['satker', 'kepatuhan', 'internal-controls'], queryFn: ({ signal }) => getOptions('/api/kontrol-internal', byKodeNama, signal) })
  const links = useQuery({ queryKey: ['satker', 'kepatuhan', 'sop-version', selectedVersion?.id, 'controls'], queryFn: ({ signal }) => getPage('/api/sop-versi/' + selectedVersion?.id + '/kontrol', { per_page: 100 }, signal), enabled: !!selectedVersion })
  const create = useMutation({ mutationFn: async () => api.post('/api/sop/' + sop.id + '/versi', { dokumen_versi_id: Number(form.dokumen_versi_id), tanggal_berlaku: form.tanggal_berlaku }), onSuccess: () => { toast.success('Versi SOP dibuat sebagai draft.'); setErrors({}); setForm({ dokumen_versi_id: '', tanggal_berlaku: '' }); void client.invalidateQueries({ queryKey: ['satker', 'kepatuhan', 'sop', sop.id, 'versions'] }) }, onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) } })
  const update = useMutation({ mutationFn: async () => { if (selectedVersion) await api.patch('/api/sop-versi/' + selectedVersion.id, { dokumen_versi_id: Number(form.dokumen_versi_id), tanggal_berlaku: form.tanggal_berlaku }) }, onSuccess: () => { toast.success('Draft versi SOP diperbarui.'); setSelectedVersion(null); setErrors({}); void client.invalidateQueries({ queryKey: ['satker', 'kepatuhan', 'sop', sop.id, 'versions'] }) }, onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) } })
  const linkControl = useMutation({ mutationFn: async () => { if (selectedVersion) await api.post('/api/sop-versi/' + selectedVersion.id + '/kontrol', { kontrol_id: Number(controlId) }) }, onSuccess: () => { toast.success('Kontrol berhasil ditautkan.'); setControlId(''); void client.invalidateQueries({ queryKey: ['satker', 'kepatuhan', 'sop-version', selectedVersion?.id, 'controls'] }) }, onError: (error) => toast.error(errorMessage(error)) })
  const review = useQuery({ queryKey: ['satker', 'kepatuhan', 'sop-review', selectedVersion?.id], queryFn: ({ signal }) => getPage('/api/sop-review', { per_page: 100, filter: { sop_versi_id: selectedVersion?.id } }, signal), enabled: !!selectedVersion })
  const beginEdit = async (row: Row) => {
    setSelectedVersion(row)
    setErrors({})
    try {
      const response = await api.get<{ data: Row }>('/api/dokumen-versi/' + row.dokumen_versi_id)
      setDocumentId(str(response.data.data.dokumen_id))
      setForm({ dokumen_versi_id: str(row.dokumen_versi_id), tanggal_berlaku: str(row.tanggal_berlaku).slice(0, 10) })
    } catch (error) {
      toast.error(errorMessage(error))
      setDocumentId('')
    }
  }
  const closeVersion = () => { setSelectedVersion(null); setErrors({}); setDocumentId(''); setForm({ dokumen_versi_id: '', tanggal_berlaku: '' }) }
  const submit = (event: FormEvent) => { event.preventDefault(); const next: Record<string, string[]> = {}; if (!form.dokumen_versi_id) next.dokumen_versi_id = ['Pilih dokumen versi FINAL.']; if (!form.tanggal_berlaku) next.tanggal_berlaku = ['Tanggal berlaku wajib diisi.']; if (Object.keys(next).length) { setErrors(next); return } setErrors({}); if (selectedVersion) update.mutate(); else create.mutate() }
  return <div className="ws-detail"><h3>Versi SOP</h3>{list.isPending ? <p role="status">Memuat versi...</p> : list.isError ? <p role="alert">{errorMessage(list.error)}</p> : !list.data.data.length ? <p>Belum ada versi SOP.</p> : <div className="ws-table-wrap"><table className="ws-table"><thead><tr><th>Versi</th><th>Status</th><th>Berlaku</th><th>Aksi</th></tr></thead><tbody>{list.data.data.map((row) => <tr key={row.id}><td>{str(row.nomor_versi)}</td><td><span className={'ws-badge ' + (row.status === 'DISAHKAN' ? 'success' : row.status === 'DITOLAK' ? 'danger' : row.status === 'DRAFT' ? 'neutral' : 'warning')}><span />{str(row.status)}</span></td><td>{str(row.tanggal_berlaku)}</td><td><button className="ws-row-open" aria-label={'Rincian versi ' + row.nomor_versi} onClick={() => void beginEdit(row)}><Edit3 size={15} /></button>{row.status === 'DRAFT' && <button type="button" onClick={() => onSubmit(row)} disabled={submitting}><Send size={15} /> Ajukan</button>}</td></tr>)}</tbody></table></div>}
    {(!selectedVersion || selectedVersion.status === 'DRAFT') && <form className="ws-profile-form" onSubmit={submit} noValidate><h4>{selectedVersion ? 'Ubah draft versi' : 'Tambah versi'}</h4><div className="ws-profile-form-grid"><Select id="source-document" label="Dokumen SOP" value={documentId} options={docOptions} error={first(errors, 'dokumen_id')} onChange={(value) => { setDocumentId(value); setForm((old) => ({ ...old, dokumen_versi_id: '' })) }} />
      <div className="ws-profile-field"><label htmlFor="source-version">Versi dokumen FINAL</label><select id="source-version" value={form.dokumen_versi_id} onChange={(event) => setForm((old) => ({ ...old, dokumen_versi_id: event.target.value }))}><option value="">Pilih versi FINAL</option>{finalOptions.map((row) => <option key={row.id} value={row.id}>Dokumen #{documentId} · v{str(row.nomor_versi)}</option>)}</select>{first(errors, 'dokumen_versi_id') && <small role="alert">{first(errors, 'dokumen_versi_id')}</small>}{selectedVersion && <small>Pilih dokumen dan versi baru jika ingin mengganti sumber versi.</small>}</div>
      <Input id="sop-effective-date" label="Tanggal berlaku" type="date" value={form.tanggal_berlaku} error={first(errors, 'tanggal_berlaku')} onChange={(value) => setForm((old) => ({ ...old, tanggal_berlaku: value }))} /></div><div className="ws-profile-actions"><button className="primary" disabled={create.isPending || update.isPending}>{create.isPending || update.isPending ? 'Menyimpan...' : selectedVersion ? 'Simpan draft' : 'Buat draft'}</button>{selectedVersion && <button type="button" onClick={closeVersion}>Batal ubah</button>}</div></form>}
    {selectedVersion && <section className="ws-panel"><h3>Kontrol tertaut dan riwayat review</h3>{selectedVersion.status === 'DRAFT' && <div className="ws-register-tools"><select aria-label="Pilih kontrol internal" value={controlId} onChange={(event) => setControlId(event.target.value)}><option value="">Pilih kontrol internal</option>{(controls.data ?? []).map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select><button type="button" disabled={!controlId || linkControl.isPending} onClick={() => linkControl.mutate()}>Hubungkan kontrol</button></div>}{links.isPending ? <p role="status">Memuat tautan...</p> : links.isError ? <p role="alert">{errorMessage(links.error)}</p> : <p>{links.data.meta.total} kontrol tertaut.</p>}<h4>Riwayat review</h4>{review.isPending ? <p role="status">Memuat riwayat...</p> : review.isError ? <p role="alert">{errorMessage(review.error)}</p> : !review.data.data.length ? <p>Belum ada catatan review.</p> : <div className="ws-table-wrap"><table className="ws-table"><thead><tr><th>Tahap</th><th>Keputusan</th><th>Reviewer</th><th>Waktu</th><th>Catatan</th></tr></thead><tbody>{review.data.data.map((row) => <tr key={row.id}><td>{str(row.tahap)}</td><td>{str(row.keputusan)}</td><td>{str(row.reviewer_id)}</td><td>{str(row.waktu)}</td><td>{str(row.catatan) || '-'}</td></tr>)}</tbody></table></div>}</section>}
  </div>
}

function Input({ id, label, value, error, type = 'text', onChange }: { id: string; label: string; value: string; error?: string; type?: string; onChange: (value: string) => void }) { return <div className="ws-profile-field"><label htmlFor={id}>{label}</label><input id={id} type={type} value={value} onChange={(event) => onChange(event.target.value)} />{error && <small role="alert">{error}</small>}</div> }
function Select({ id, label, value, options, error, onChange }: { id: string; label: string; value: string; options: Option[]; error?: string; onChange: (value: string) => void }) { return <div className="ws-profile-field"><label htmlFor={id}>{label}</label><select id={id} value={value} onChange={(event) => onChange(event.target.value)}><option value="">Pilih {label.toLowerCase()}</option>{options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}</select>{error && <small role="alert">{error}</small>}</div> }
