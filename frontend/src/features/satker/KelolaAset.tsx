import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ChevronRight, Edit3, Plus, RefreshCw, Search, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import {
  byKodeNama,
  byPegawai,
  dateOnly,
  getOptions,
  getPage,
  type Option,
  type Row,
} from './api'

type Lookups = { unit: Option[]; kategori: Option[]; klasifikasi: Option[]; pegawai: Option[] }

const str = (value: unknown) => (value == null ? '' : String(value))
const labelOf = (options: Option[] | undefined, id: unknown) =>
  options?.find((o) => String(o.id) === str(id))?.label ?? (id == null || id === '' ? '-' : '#' + str(id))

function useLookups(): { data: Lookups; isPending: boolean } {
  const unit = useQuery({ queryKey: ['satker', 'opt', 'unit'], queryFn: ({ signal }) => getOptions('/api/unit-organisasi', byKodeNama, signal), staleTime: 300_000 })
  const kategori = useQuery({ queryKey: ['satker', 'opt', 'kategori'], queryFn: ({ signal }) => getOptions('/api/kategori-aset', byKodeNama, signal), staleTime: 300_000 })
  const klasifikasi = useQuery({ queryKey: ['satker', 'opt', 'klasifikasi'], queryFn: ({ signal }) => getOptions('/api/klasifikasi-informasi', byKodeNama, signal), staleTime: 300_000 })
  const pegawai = useQuery({ queryKey: ['satker', 'opt', 'pegawai'], queryFn: ({ signal }) => getOptions('/api/pegawai', byPegawai, signal), staleTime: 300_000 })
  return {
    data: {
      unit: unit.data ?? [],
      kategori: kategori.data ?? [],
      klasifikasi: klasifikasi.data ?? [],
      pegawai: pegawai.data ?? [],
    },
    isPending: unit.isPending || kategori.isPending || klasifikasi.isPending || pegawai.isPending,
  }
}

export function KelolaAset() {
  const [page, setPage] = useState(1)
  const [draft, setDraft] = useState('')
  const [q, setQ] = useState('')
  const [reviewOnly, setReviewOnly] = useState(false)
  const [selected, setSelected] = useState<Row | null>(null)
  const lookups = useLookups()

  const filter = reviewOnly ? { perlu_ditinjau: true } : undefined
  const list = useQuery({
    queryKey: ['satker', 'aset', page, q, reviewOnly],
    queryFn: ({ signal }) =>
      getPage('/api/aset', { page, sort: '-id', ...(q ? { q } : {}), ...(filter ? { filter } : {}) }, signal),
  })

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">Pengelolaan satker</span>
          <h1>Kelola aset &amp; penugasan</h1>
          <p>Ubah data lokal aset dan tetapkan penanggung jawab. Data sumber tidak dapat diubah.</p>
        </div>
      </div>
      <section className="ws-panel">
        <div className="ws-register-tools">
          <form
            className="ws-filter"
            onSubmit={(e) => {
              e.preventDefault()
              setQ(draft.trim())
              setPage(1)
            }}
          >
            <Search size={17} />
            <input
              aria-label="Cari aset"
              placeholder="Cari nama, merk, nomor seri, atau nomor inventaris..."
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
            />
            <button type="submit">Cari</button>
          </form>
          <button
            type="button"
            aria-pressed={reviewOnly}
            onClick={() => {
              setReviewOnly((v) => !v)
              setPage(1)
            }}
          >
            Perlu ditinjau saja
          </button>
          {(q || reviewOnly) && (
            <button
              type="button"
              onClick={() => {
                setDraft('')
                setQ('')
                setReviewOnly(false)
                setPage(1)
              }}
            >
              Reset filter
            </button>
          )}
          <button
            type="button"
            className="ws-refresh"
            aria-label="Perbarui data"
            disabled={list.isFetching}
            onClick={() => void list.refetch()}
          >
            <RefreshCw size={16} />
          </button>
        </div>
        {list.isPending ? (
          <div className="ws-loading" role="status">
            <span>Memuat data...</span>
          </div>
        ) : list.isError ? (
          <div className="ws-empty" role="alert">
            <strong>Data belum dapat dimuat</strong>
            <p>{errorMessage(list.error)}</p>
            <button type="button" onClick={() => void list.refetch()}>
              Coba lagi
            </button>
          </div>
        ) : !list.data.data.length ? (
          <div className="ws-empty">
            <Search size={30} />
            <strong>Tidak ada aset yang ditemukan</strong>
            <p>Coba ubah kata kunci atau periksa kembali lingkup satker Anda.</p>
          </div>
        ) : (
          <>
            <div className="ws-table-wrap">
              <table className="ws-table">
                <caption className="sr-only">Daftar aset satker</caption>
                <thead>
                  <tr>
                    <th>Kode aset</th>
                    <th>Nama</th>
                    <th>Unit pemilik</th>
                    <th>Kondisi</th>
                    <th>Penilaian</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {list.data.data.map((row) => {
                    const needsReview = row.perlu_ditinjau === true || row.perlu_ditinjau === 1 || row.perlu_ditinjau === '1'
                    return (
                      <tr key={row.id}>
                        <td>{str(row.kode_aset) || '-'}</td>
                        <td>
                          <button type="button" className="ws-cell-link" onClick={() => setSelected(row)}>
                            {str(row.nama) || '#' + row.id}
                          </button>
                        </td>
                        <td>{labelOf(lookups.data.unit, row.unit_pemilik_id)}</td>
                        <td>{str(row.kondisi) || '-'}</td>
                        <td>
                          <span className={'ws-badge ' + (needsReview ? 'danger' : 'success')}>
                            <span />
                            {needsReview ? 'BELUM DINILAI' : 'SUDAH DINILAI'}
                          </span>
                        </td>
                        <td>
                          <button
                            type="button"
                            className="ws-row-open"
                            aria-label={'Kelola aset ' + row.id}
                            onClick={() => setSelected(row)}
                          >
                            <Edit3 size={17} />
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            <div className="ws-pagination">
              <span>
                {list.data.meta.from || 0}-{list.data.meta.to || 0} dari {list.data.meta.total} data
              </span>
              <div>
                <button type="button" aria-label="Halaman sebelumnya" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  <ChevronLeft size={16} />
                </button>
                <span>
                  Halaman {list.data.meta.current_page} / {list.data.meta.last_page}
                </span>
                <button
                  type="button"
                  aria-label="Halaman berikutnya"
                  disabled={page >= list.data.meta.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
                  <ChevronRight size={16} />
                </button>
              </div>
            </div>
          </>
        )}
      </section>
      <Drawer
        open={!!selected}
        onClose={() => setSelected(null)}
        title={'Aset #' + (selected?.id ?? '')}
        description="Ubah data lokal aset dan kelola penanggung jawabnya."
      >
        {selected && (
          <AsetEditor
            key={selected.id}
            row={selected}
            lookups={lookups.data}
            onSaved={() => setSelected(null)}
          />
        )}
      </Drawer>
    </>
  )
}

function Select({
  id,
  label,
  value,
  onChange,
  options,
  placeholder,
  error,
  disabled,
}: {
  id: string
  label: string
  value: string
  onChange: (value: string) => void
  options: Option[]
  placeholder: string
  error?: string
  disabled?: boolean
}) {
  return (
    <div className="ws-profile-field">
      <label htmlFor={id}>{label}</label>
      <select id={id} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)}>
        <option value="">{placeholder}</option>
        {options.map((o) => (
          <option key={o.id} value={String(o.id)}>
            {o.label}
          </option>
        ))}
      </select>
      {error && (
        <small role="alert" style={{ color: '#b91c2c' }}>
          {error}
        </small>
      )}
    </div>
  )
}

function AsetEditor({ row, lookups, onSaved }: { row: Row; lookups: Lookups; onSaved: () => void }) {
  const client = useQueryClient()
  const initial = {
    unit: str(row.unit_pemilik_id),
    kategori: str(row.kategori_id),
    klasifikasi: str(row.klasifikasi_informasi_id),
    lokasi: str(row.lokasi),
    deskripsi: str(row.deskripsi),
  }
  const [form, setForm] = useState(initial)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const set = (key: keyof typeof initial) => (value: string) => setForm((f) => ({ ...f, [key]: value }))

  const save = useMutation({
    mutationFn: (body: Record<string, unknown>) => api.patch('/api/aset/' + row.id, body),
    onSuccess: () => {
      toast.success('Data aset berhasil diperbarui')
      void client.invalidateQueries({ queryKey: ['satker'] })
      onSaved()
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(errorMessage(error))
    },
  })

  function submit(e: FormEvent) {
    e.preventDefault()
    // Hanya kolom yang berubah yang dikirim. Kolom kosong tidak dikirim karena backend menolak nilai kosong.
    const body: Record<string, unknown> = {}
    if (form.unit !== initial.unit && form.unit) body.unit_pemilik_id = Number(form.unit)
    if (form.kategori !== initial.kategori && form.kategori) body.kategori_id = Number(form.kategori)
    if (form.klasifikasi !== initial.klasifikasi) body.klasifikasi_informasi_id = form.klasifikasi ? Number(form.klasifikasi) : null
    if (form.lokasi !== initial.lokasi && form.lokasi.trim()) body.lokasi = form.lokasi.trim()
    if (form.deskripsi !== initial.deskripsi && form.deskripsi.trim()) body.deskripsi = form.deskripsi.trim()
    if (!Object.keys(body).length) {
      toast.info('Tidak ada perubahan untuk disimpan.')
      return
    }
    setErrors({})
    save.mutate(body)
  }

  const first = (field: string) => errors[field]?.[0]

  return (
    <div className="ws-detail">
      <dl>
        <div>
          <dt>Kode aset</dt>
          <dd>{str(row.kode_aset) || '-'}</dd>
        </div>
        <div>
          <dt>Nama</dt>
          <dd>{str(row.nama) || '-'}</dd>
        </div>
        <div>
          <dt>Kondisi</dt>
          <dd>{str(row.kondisi) || '-'}</dd>
        </div>
      </dl>
      <form className="ws-profile-form" onSubmit={submit} noValidate>
        <h3>Data lokal aset</h3>
        <div className="ws-profile-form-grid">
          <Select id="aset-unit" label="Unit pemilik" value={form.unit} onChange={set('unit')} options={lookups.unit} placeholder="Pilih unit" error={first('unit_pemilik_id')} disabled={save.isPending} />
          <Select id="aset-kategori" label="Kategori aset" value={form.kategori} onChange={set('kategori')} options={lookups.kategori} placeholder="Pilih kategori" error={first('kategori_id')} disabled={save.isPending} />
          <Select id="aset-klasifikasi" label="Klasifikasi informasi" value={form.klasifikasi} onChange={set('klasifikasi')} options={lookups.klasifikasi} placeholder="Tanpa klasifikasi" error={first('klasifikasi_informasi_id')} disabled={save.isPending} />
          <div className="ws-profile-field">
            <label htmlFor="aset-lokasi">Lokasi</label>
            <input id="aset-lokasi" type="text" maxLength={255} value={form.lokasi} disabled={save.isPending} onChange={(e) => set('lokasi')(e.target.value)} />
            {first('lokasi') && <small role="alert" style={{ color: '#b91c2c' }}>{first('lokasi')}</small>}
          </div>
          <div className="ws-profile-field">
            <label htmlFor="aset-deskripsi">Deskripsi</label>
            <textarea id="aset-deskripsi" rows={3} maxLength={10000} value={form.deskripsi} disabled={save.isPending} onChange={(e) => set('deskripsi')(e.target.value)} />
            {first('deskripsi') && <small role="alert" style={{ color: '#b91c2c' }}>{first('deskripsi')}</small>}
          </div>
        </div>
        <div className="ws-profile-actions">
          <button type="submit" className="primary" disabled={save.isPending}>
            {save.isPending ? 'Menyimpan…' : 'Simpan perubahan'}
          </button>
        </div>
      </form>
      <Penugasan asetId={row.id} pegawai={lookups.pegawai} />
    </div>
  )
}

type PenugasanForm = { pegawai: string; jenis: string; mulai: string; selesai: string }
const emptyPenugasan: PenugasanForm = { pegawai: '', jenis: '', mulai: '', selesai: '' }

function Penugasan({ asetId, pegawai }: { asetId: number; pegawai: Option[] }) {
  const client = useQueryClient()
  const [editing, setEditing] = useState<Row | null>(null)
  const [form, setForm] = useState<PenugasanForm>(emptyPenugasan)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [removing, setRemoving] = useState<Row | null>(null)
  const [removePending, setRemovePending] = useState(false)

  const list = useQuery({
    queryKey: ['satker', 'penugasan', asetId],
    queryFn: ({ signal }) => getPage('/api/penugasan-aset', { per_page: 50, sort: '-id', filter: { aset_id: asetId } }, signal),
  })
  const refresh = () => void client.invalidateQueries({ queryKey: ['satker', 'penugasan', asetId] })

    const save = useMutation({
    mutationFn: async (body: Record<string, unknown>) => {
      if (editing) await api.patch('/api/penugasan-aset/' + editing.id, body)
      else await api.post('/api/penugasan-aset', { aset_id: asetId, ...body })
    },
    onSuccess: () => {
      toast.success(editing ? 'Penugasan diperbarui' : 'Penugasan ditambahkan')
      setEditing(null)
      setForm(emptyPenugasan)
      setErrors({})
      refresh()
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(errorMessage(error))
    },
  })

  function startEdit(row: Row) {
    setEditing(row)
    setErrors({})
    setForm({ pegawai: str(row.pegawai_id), jenis: str(row.jenis_tanggung_jawab), mulai: dateOnly(row.mulai), selesai: dateOnly(row.selesai) })
  }
  function cancel() {
    setEditing(null)
    setErrors({})
    setForm(emptyPenugasan)
  }
  function submit(e: FormEvent) {
    e.preventDefault()
    const missing: Record<string, string[]> = {}
    if (!form.pegawai) missing.pegawai_id = ['Pilih pegawai.']
    if (!form.jenis.trim()) missing.jenis_tanggung_jawab = ['Jenis tanggung jawab wajib diisi.']
    if (!form.mulai) missing.mulai = ['Tanggal mulai wajib diisi.']
    if (Object.keys(missing).length) {
      setErrors(missing)
      return
    }
    setErrors({})
    const body: Record<string, unknown> = {
      pegawai_id: Number(form.pegawai),
      jenis_tanggung_jawab: form.jenis.trim(),
      mulai: form.mulai,
    }
    if (editing || form.selesai) body.selesai = form.selesai || null
    save.mutate(body)
  }

  const first = (field: string) => errors[field]?.[0]

  return (
    <>
      <h3>Penanggung jawab aset</h3>
      {list.isPending ? (
        <p role="status">Memuat penugasan…</p>
      ) : list.isError ? (
        <p role="alert">{errorMessage(list.error)}</p>
      ) : !list.data.data.length ? (
        <p>Belum ada penugasan untuk aset ini.</p>
      ) : (
        <div className="ws-table-wrap">
          <table className="ws-table">
            <thead>
              <tr>
                <th>Pegawai</th>
                <th>Tanggung jawab</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {list.data.data.map((row) => (
                <tr key={row.id}>
                  <td>{labelOf(pegawai, row.pegawai_id)}</td>
                  <td>{str(row.jenis_tanggung_jawab) || '-'}</td>
                  <td>{dateOnly(row.mulai) || '-'}</td>
                  <td>{dateOnly(row.selesai) || '-'}</td>
                  <td>
                    <button type="button" className="ws-row-open" aria-label={'Ubah penugasan ' + row.id} onClick={() => startEdit(row)}>
                      <Edit3 size={16} />
                    </button>
                    <button type="button" className="ws-row-open" aria-label={'Hapus penugasan ' + row.id} onClick={() => setRemoving(row)}>
                      <Trash2 size={16} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <form className="ws-profile-form" onSubmit={submit} noValidate>
        <h3>{editing ? 'Ubah penugasan #' + editing.id : 'Tambah penugasan'}</h3>
        <div className="ws-profile-form-grid">
          <Select id="tugas-pegawai" label="Pegawai" value={form.pegawai} onChange={(v) => setForm((f) => ({ ...f, pegawai: v }))} options={pegawai} placeholder="Pilih pegawai" error={first('pegawai_id')} disabled={save.isPending} />
          <div className="ws-profile-field">
            <label htmlFor="tugas-jenis">Jenis tanggung jawab</label>
            <input id="tugas-jenis" type="text" maxLength={255} value={form.jenis} disabled={save.isPending} onChange={(e) => setForm((f) => ({ ...f, jenis: e.target.value }))} placeholder="Contoh: Penanggung jawab" />
            {first('jenis_tanggung_jawab') && <small role="alert" style={{ color: '#b91c2c' }}>{first('jenis_tanggung_jawab')}</small>}
          </div>
          <div className="ws-profile-field">
            <label htmlFor="tugas-mulai">Mulai</label>
            <input id="tugas-mulai" type="date" value={form.mulai} disabled={save.isPending} onChange={(e) => setForm((f) => ({ ...f, mulai: e.target.value }))} />
            {first('mulai') && <small role="alert" style={{ color: '#b91c2c' }}>{first('mulai')}</small>}
          </div>
          <div className="ws-profile-field">
            <label htmlFor="tugas-selesai">Selesai (opsional)</label>
            <input id="tugas-selesai" type="date" value={form.selesai} disabled={save.isPending} onChange={(e) => setForm((f) => ({ ...f, selesai: e.target.value }))} />
            {first('selesai') && <small role="alert" style={{ color: '#b91c2c' }}>{first('selesai')}</small>}
          </div>
        </div>
        <div className="ws-profile-actions">
          <button type="submit" className="primary" disabled={save.isPending}>
            <Plus size={16} />
            {save.isPending ? 'Menyimpan…' : editing ? 'Simpan penugasan' : 'Tambah penugasan'}
          </button>
          {editing && (
            <button type="button" disabled={save.isPending} onClick={cancel}>
              Batal
            </button>
          )}
        </div>
      </form>
      <ConfirmDialog
        open={!!removing}
        onClose={() => setRemoving(null)}
        title="Hapus penugasan"
        description={['Penugasan ini akan dihapus dari aset. Tindakan ini tercatat di jejak audit.']}
        confirmLabel="Ya, hapus"
        pending={removePending}
        onConfirm={async () => {
          if (!removing) return
          setRemovePending(true)
          try {
            await api.delete('/api/penugasan-aset/' + removing.id)
            toast.success('Penugasan dihapus')
            setRemoving(null)
            refresh()
          } catch (error) {
            toast.error(errorMessage(error))
          } finally {
            setRemovePending(false)
          }
        }}
      />
    </>
  )
}