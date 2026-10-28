import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import { byKodeNama, getOptions, getPage, type Option, type Row } from './api'
import { Download, Edit3, FileText, Plus, RefreshCw, Trash2, Upload } from 'lucide-react'

type DocumentForm = { unit_pemilik_id: string; kode: string; judul: string; jenis: string }
type VersionForm = { isi_teks: string; format_teks: string }
const blankDocument: DocumentForm = { unit_pemilik_id: '', kode: '', judul: '', jenis: 'SOP' }
const blankVersion: VersionForm = { isi_teks: '', format_teks: 'PLAIN' }
const str = (value: unknown) => value == null ? '' : String(value)
const errorFor = (errors: Record<string, string[]>, key: string) => errors[key]?.[0]

export function KelolaDokumen() {
  const client = useQueryClient()
  const [page, setPage] = useState(1)
  const [kind, setKind] = useState('')
  const [selected, setSelected] = useState<Row | null>(null)
  const [creating, setCreating] = useState(false)
  const [form, setForm] = useState<DocumentForm>(blankDocument)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const units = useQuery({ queryKey: ['satker', 'dokumen', 'units'], queryFn: ({ signal }) => getOptions('/api/unit-organisasi', byKodeNama, signal) })
  const list = useQuery({ queryKey: ['satker', 'dokumen', page, kind], queryFn: ({ signal }) => getPage('/api/dokumen', { page, per_page: 10, sort: '-id', ...(kind ? { filter: { jenis: kind } } : {}) }, signal) })
  const refresh = () => { void client.invalidateQueries({ queryKey: ['satker', 'dokumen'] }) }
  const create = useMutation({ mutationFn: async () => api.post('/api/dokumen', { ...form, unit_pemilik_id: Number(form.unit_pemilik_id), kode: form.kode.trim(), judul: form.judul.trim() }), onSuccess: () => { toast.success('Dokumen berhasil dibuat.'); setCreating(false); setForm(blankDocument); setErrors({}); refresh() }, onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) } })
  const submit = (event: FormEvent) => { event.preventDefault(); const next: Record<string, string[]> = {}; if (!form.unit_pemilik_id) next.unit_pemilik_id = ['Pilih unit pemilik.']; for (const key of ['kode', 'judul', 'jenis'] as const) if (!form[key].trim()) next[key] = ['Kolom wajib diisi.']; if (Object.keys(next).length) { setErrors(next); return } setErrors({}); create.mutate() }

  const openCreate = () => {
    const defaultUnitId = units.data?.length === 1 ? String(units.data[0].id) : ''
    setForm({ ...blankDocument, unit_pemilik_id: defaultUnitId })
    setErrors({})
    setCreating(true)
  }

  return <>
    <div className="ws-page-heading"><div><span className="ws-eyebrow">Pengelolaan satker</span><h1>Dokumen &amp; bukti</h1><p>Buat dokumen, kelola versi draft, lampiran, dan finalisasi.</p></div><button className="primary" onClick={openCreate}><Plus size={16} /> Tambah dokumen</button></div>
    <section className="ws-panel"><div className="ws-register-tools"><label className="ws-profile-field"><span>Jenis dokumen</span><select aria-label="Filter jenis dokumen" value={kind} onChange={(event) => { setKind(event.target.value); setPage(1) }}><option value="">Semua jenis</option>{['SOP', 'BUKTI', 'LAPORAN', 'PENDUKUNG'].map((item) => <option key={item}>{item}</option>)}</select></label><button className="ws-refresh" aria-label="Perbarui dokumen" disabled={list.isFetching} onClick={() => void list.refetch()}><RefreshCw size={16} /></button></div>
      {list.isPending ? <div className="ws-loading" role="status">Memuat dokumen...</div> : list.isError ? <div className="ws-empty" role="alert"><strong>Dokumen belum dapat dimuat</strong><p>{errorMessage(list.error)}</p><button onClick={() => void list.refetch()}>Coba lagi</button></div> : !list.data.data.length ? <div className="ws-empty"><FileText size={28} /><strong>Belum ada dokumen</strong><p>Buat dokumen pertama dalam lingkup satker Anda.</p></div> : <>
        <div className="ws-table-wrap"><table className="ws-table"><thead><tr><th>Kode</th><th>Judul</th><th>Jenis</th><th>Unit</th><th>Aksi</th></tr></thead><tbody>{list.data.data.map((row) => <tr key={row.id}><td>{str(row.kode)}</td><td>{str(row.judul)}</td><td>{str(row.jenis)}</td><td>{units.data?.find((unit) => unit.id === Number(row.unit_pemilik_id))?.label ?? '#' + row.unit_pemilik_id}</td><td><button className="ws-row-open" aria-label={'Kelola dokumen ' + row.kode} onClick={() => setSelected(row)}><Edit3 size={16} /></button></td></tr>)}</tbody></table></div>
        <div className="ws-pagination"><span>{list.data.meta.from || 0}-{list.data.meta.to || 0} dari {list.data.meta.total} dokumen</span><div><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Sebelumnya</button><span>{list.data.meta.current_page} / {list.data.meta.last_page}</span><button disabled={page >= list.data.meta.last_page} onClick={() => setPage((value) => value + 1)}>Berikutnya</button></div></div>
      </>}
    </section>
    <Drawer open={creating} onClose={() => setCreating(false)} title="Buat dokumen" description="Kode dokumen bersifat unik per unit organisasi dalam satker."><form className="ws-profile-form" onSubmit={submit} noValidate><div className="ws-profile-form-grid"><Select id="doc-unit" label="Unit pemilik" value={form.unit_pemilik_id} options={units.data ?? []} error={errorFor(errors, 'unit_pemilik_id')} onChange={(value) => setForm((old) => ({ ...old, unit_pemilik_id: value }))} /><Input id="doc-code" label="Kode" value={form.kode} error={errorFor(errors, 'kode')} onChange={(value) => setForm((old) => ({ ...old, kode: value }))} /><Input id="doc-title" label="Judul" value={form.judul} error={errorFor(errors, 'judul')} onChange={(value) => setForm((old) => ({ ...old, judul: value }))} /><div className="ws-profile-field"><label htmlFor="doc-kind">Jenis</label><select id="doc-kind" value={form.jenis} onChange={(event) => setForm((old) => ({ ...old, jenis: event.target.value }))}>{['SOP', 'BUKTI', 'LAPORAN', 'PENDUKUNG'].map((item) => <option key={item}>{item}</option>)}</select>{errorFor(errors, 'jenis') && <small role="alert">{errorFor(errors, 'jenis')}</small>}</div></div><div className="ws-profile-actions"><button className="primary" disabled={create.isPending}>{create.isPending ? 'Membuat...' : 'Buat dokumen'}</button></div></form></Drawer>
    <Drawer open={!!selected} onClose={() => setSelected(null)} title={'Dokumen ' + str(selected?.kode)} description={str(selected?.judul)}>{selected && <DocumentVersions document={selected} />}</Drawer>
  </>
}

function DocumentVersions({ document }: { document: Row }) {
  const client = useQueryClient()
  const [editing, setEditing] = useState<Row | null>(null)
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [fileNote, setFileNote] = useState('')
  const [finalizing, setFinalizing] = useState<Row | null>(null)
  const [detaching, setDetaching] = useState<{ version: Row; file: Row } | null>(null)
  const [form, setForm] = useState<VersionForm>(blankVersion)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const versions = useQuery({ queryKey: ['satker', 'dokumen', document.id, 'versions'], queryFn: ({ signal }) => getPage('/api/dokumen/' + document.id + '/versi', { per_page: 100, sort: '-id' }, signal) })
  const refresh = () => { void client.invalidateQueries({ queryKey: ['satker', 'dokumen', document.id, 'versions'] }); void client.invalidateQueries({ queryKey: ['satker', 'dokumen'] }) }
  const save = useMutation({ mutationFn: async () => { if (editing) await api.patch('/api/dokumen-versi/' + editing.id, form); else await api.post('/api/dokumen/' + document.id + '/versi', form) }, onSuccess: () => { toast.success(editing ? 'Draft versi diperbarui.' : 'Draft versi dibuat.'); setEditing(null); setForm(blankVersion); setErrors({}); refresh() }, onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) } })
  const final = useMutation({ mutationFn: async () => { if (finalizing) await api.post('/api/dokumen-versi/' + finalizing.id + '/finalkan') }, onSuccess: () => { toast.success('Versi dokumen difinalkan.'); setFinalizing(null); refresh() }, onError: (error) => toast.error(errorMessage(error)) })
  const upload = useMutation({ mutationFn: async ({ version, file }: { version: Row; file: File }) => { const data = new FormData(); data.append('dokumen_versi_id', String(version.id)); data.append('file', file); if (fileNote.trim()) data.append('keterangan', fileNote.trim()); await api.post('/api/file-lampiran', data) }, onSuccess: () => { toast.success('Lampiran diunggah dan menunggu pemeriksaan.'); setSelectedFile(null); setFileNote(''); refresh() }, onError: (error) => { setErrors(fieldErrors(error)); toast.error(errorMessage(error)) } })
  const detach = useMutation({ mutationFn: async () => { if (detaching) await api.delete('/api/dokumen-versi/' + detaching.version.id + '/file/' + detaching.file.id) }, onSuccess: () => { toast.success('Lampiran dilepas dari draft.'); setDetaching(null); refresh() }, onError: (error) => toast.error(errorMessage(error)) })
  const startEdit = (row: Row) => { setEditing(row); setErrors({}); setForm({ isi_teks: str(row.isi_teks), format_teks: str(row.format_teks) || 'PLAIN' }) }
  const submit = (event: FormEvent) => { event.preventDefault(); if (!form.isi_teks.trim() && !editing) { setErrors({ isi_teks: ['Isi teks atau lampiran diperlukan sebelum finalisasi.'] }); } else { setErrors({}); save.mutate() } }
  async function download(file: Row) { try { const response = await api.get('/api/file-lampiran/' + file.id + '/unduh', { responseType: 'blob' }); const url = URL.createObjectURL(response.data); const anchor = window.document.createElement('a'); anchor.href = url; anchor.download = str(file.nama_asli) || `lampiran-${file.id}`; anchor.click(); URL.revokeObjectURL(url) } catch (error) { toast.error(errorMessage(error)) } }
  return <div className="ws-detail"><h3>Versi dokumen</h3>{versions.isPending ? <p role="status">Memuat versi...</p> : versions.isError ? <p role="alert">{errorMessage(versions.error)}</p> : !versions.data.data.length ? <p>Belum ada versi. Buat draft di bawah.</p> : versions.data.data.map((version) => {
    const files = Array.isArray(version.file_lampiran) ? version.file_lampiran.filter((file): file is Row => typeof file === 'object' && file !== null && 'id' in file) : []
    return <details key={version.id} className="ws-related"><summary>Versi {str(version.nomor_versi)} · <span className={'ws-badge ' + (version.status === 'FINAL' ? 'success' : 'neutral')}><span />{str(version.status)}</span></summary><p>Format: {str(version.format_teks)} · Dibuat {str(version.dibuat_pada)}</p>{version.status === 'DRAFT' && <><button type="button" onClick={() => startEdit(version)}><Edit3 size={15} /> Ubah draft</button><div className="ws-profile-form"><label htmlFor={'upload-' + version.id}>Lampiran draft</label><input id={'upload-' + version.id} type="file" onChange={(event) => setSelectedFile(event.target.files?.[0] ?? null)} /><Input id={'file-note-' + version.id} label="Keterangan lampiran" value={fileNote} onChange={setFileNote} /><button type="button" disabled={!selectedFile || upload.isPending} onClick={() => selectedFile && upload.mutate({ version, file: selectedFile })}><Upload size={15} /> Unggah lampiran</button><button type="button" disabled={final.isPending} onClick={() => setFinalizing(version)}>Finalkan versi</button></div></>}{files.length ? <div className="ws-table-wrap"><table className="ws-table"><thead><tr><th>Lampiran</th><th>Status</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>{files.map((file) => <tr key={file.id}><td>{str(file.nama_asli)}</td><td>{str(file.status_pemeriksaan)}</td><td>{str(file.keterangan) || '-'}</td><td><button type="button" disabled={file.status_pemeriksaan !== 'AMAN'} onClick={() => void download(file)} aria-label={'Unduh ' + file.nama_asli}><Download size={15} /></button>{version.status === 'DRAFT' && <button type="button" onClick={() => setDetaching({ version, file })} aria-label={'Lepas ' + file.nama_asli}><Trash2 size={15} /></button>}</td></tr>)}</tbody></table></div> : <p>Lampiran belum tersedia pada data versi ini.</p>}</details>
  })}
    <form className="ws-profile-form" onSubmit={submit} noValidate><h4>{editing ? 'Ubah draft versi' : 'Buat versi baru'}</h4><div className="ws-profile-form-grid"><div className="ws-profile-field"><label htmlFor="version-format">Format teks</label><select id="version-format" value={form.format_teks} onChange={(event) => setForm((old) => ({ ...old, format_teks: event.target.value }))}>{['PLAIN', 'MARKDOWN', 'RICH_TEXT_JSON'].map((item) => <option key={item}>{item}</option>)}</select>{errorFor(errors, 'format_teks') && <small role="alert">{errorFor(errors, 'format_teks')}</small>}</div><div className="ws-profile-field"><label htmlFor="version-content">Isi teks</label><textarea id="version-content" rows={8} maxLength={1000000} value={form.isi_teks} onChange={(event) => setForm((old) => ({ ...old, isi_teks: event.target.value }))} />{errorFor(errors, 'isi_teks') && <small role="alert">{errorFor(errors, 'isi_teks')}</small>}</div></div><div className="ws-profile-actions"><button className="primary" disabled={save.isPending}>{save.isPending ? 'Menyimpan...' : editing ? 'Simpan draft' : 'Buat draft'}</button>{editing && <button type="button" onClick={() => { setEditing(null); setForm(blankVersion) }}>Batal ubah</button>}</div></form>
    <ConfirmDialog open={!!finalizing} onClose={() => setFinalizing(null)} title="Finalkan versi dokumen?" description={['Versi akan dikunci setelah finalisasi.', 'Lampiran harus berstatus AMAN dan isi atau lampiran harus tersedia.']} confirmLabel="Finalkan" pending={final.isPending} onConfirm={async () => { if (finalizing) await final.mutateAsync() }} />
    <ConfirmDialog open={!!detaching} onClose={() => setDetaching(null)} title="Lepas lampiran?" description={[`Lampiran ${str(detaching?.file.nama_asli)} akan dilepas dari draft ini.`]} confirmLabel="Lepas lampiran" pending={detach.isPending} onConfirm={async () => { if (detaching) await detach.mutateAsync() }} />
  </div>
}

function Input({ id, label, value, error, onChange }: { id: string; label: string; value: string; error?: string; onChange: (value: string) => void }) { return <div className="ws-profile-field"><label htmlFor={id}>{label}</label><input id={id} value={value} onChange={(event) => onChange(event.target.value)} />{error && <small role="alert">{error}</small>}</div> }
function Select({ id, label, value, options, error, onChange }: { id: string; label: string; value: string; options: Option[]; error?: string; onChange: (value: string) => void }) { return <div className="ws-profile-field"><label htmlFor={id}>{label}</label><select id={id} value={value} onChange={(event) => onChange(event.target.value)}><option value="">Pilih {label.toLowerCase()}</option>{options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}</select>{error && <small role="alert">{error}</small>}</div> }
