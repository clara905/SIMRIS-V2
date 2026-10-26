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
import { isAssetPendingAssessment } from '../aset/assessment'

type Lookups = {
  unit: Option[]
  kategori: Option[]
  klasifikasi: Option[]
  pegawai: Option[]
  sumber: Option[]
}

const str = (value: unknown) => (value == null ? '' : String(value))
const labelOf = (options: Option[] | undefined, id: unknown) =>
  options?.find((o) => String(o.id) === str(id))?.label ?? (id == null || id === '' ? '-' : '#' + str(id))

type BulkResult = { berhasil: number; gagal: number; masalah: Array<{ id: number; pesan: string }> }

function useLookups(): { data: Lookups; isPending: boolean } {
  const unit = useQuery({ queryKey: ['satker', 'opt', 'unit'], queryFn: ({ signal }) => getOptions('/api/unit-organisasi', byKodeNama, signal), staleTime: 300_000 })
  const kategori = useQuery({ queryKey: ['satker', 'opt', 'kategori'], queryFn: ({ signal }) => getOptions('/api/kategori-aset', byKodeNama, signal), staleTime: 300_000 })
  const klasifikasi = useQuery({ queryKey: ['satker', 'opt', 'klasifikasi'], queryFn: ({ signal }) => getOptions('/api/klasifikasi-informasi', byKodeNama, signal), staleTime: 300_000 })
  const pegawai = useQuery({ queryKey: ['satker', 'opt', 'pegawai'], queryFn: ({ signal }) => getOptions('/api/pegawai', byPegawai, signal), staleTime: 300_000 })
  const sumber = useQuery({ queryKey: ['satker', 'opt', 'sumber-aset'], queryFn: ({ signal }) => getOptions('/api/sumber-aset', byKodeNama, signal), staleTime: 300_000 })
  return {
    data: {
      unit: unit.data ?? [],
      kategori: kategori.data ?? [],
      klasifikasi: klasifikasi.data ?? [],
      pegawai: pegawai.data ?? [],
      sumber: sumber.data ?? [],
    },
    isPending: unit.isPending || kategori.isPending || klasifikasi.isPending || pegawai.isPending || sumber.isPending,
  }
}

export function KelolaAset() {
  const [page, setPage] = useState(1)
  const [draft, setDraft] = useState('')
  const [q, setQ] = useState('')
  const [assessment, setAssessment] = useState<'all' | 'pending' | 'assessed'>('all')
  const [selected, setSelected] = useState<Row | null>(null)
  const [selectedIds, setSelectedIds] = useState<number[]>([])
  const [bulkUnit, setBulkUnit] = useState('')
  const [bulkCategory, setBulkCategory] = useState('')
  const [bulkResult, setBulkResult] = useState<BulkResult | null>(null)
  const [bulkErrors, setBulkErrors] = useState<Record<string, string[]>>({})
  const lookups = useLookups()
  const client = useQueryClient()

  const filter = assessment === 'all' ? undefined : { perlu_ditinjau: assessment === 'pending' }
  const list = useQuery({
    queryKey: ['satker', 'aset', page, q, assessment],
    queryFn: ({ signal }) =>
      getPage('/api/aset', { page, sort: '-id', ...(q ? { q } : {}), ...(filter ? { filter } : {}) }, signal),
  })
  const bulkAssign = useMutation({
    mutationFn: async () => {
      const response = await api.post<{ data: BulkResult }>('/api/aset/tetapkan', {
        ids: selectedIds,
        unit_pemilik_id: Number(bulkUnit),
        kategori_id: Number(bulkCategory),
      })
      return response.data.data
    },
    onSuccess: (result) => {
      setBulkResult(result)
      setSelectedIds([])
      toast.success(`${result.berhasil} aset berhasil ditetapkan${result.gagal ? `, ${result.gagal} gagal` : ''}.`)
      void client.invalidateQueries({ queryKey: ['satker'] })
    },
    onError: (error) => { setBulkErrors(fieldErrors(error)); toast.error(errorMessage(error)) },
  })
  const pendingRows = list.data?.data.filter(isAssetPendingAssessment) ?? []
  const allowedCategories = lookups.data.kategori.filter((item) => !item.label.toUpperCase().includes('BELUM_DIKATEGORIKAN'))
  const toggleAsset = (id: number, checked: boolean) => setSelectedIds((ids) => {
    if (checked && !ids.includes(id) && ids.length >= 100) {
      toast.info('Maksimal 100 aset dapat dipilih sekaligus.')
      return ids
    }
    return checked ? [...new Set([...ids, id])] : ids.filter((value) => value !== id)
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
            aria-pressed={assessment === 'pending'}
            onClick={() => {
              setAssessment((value) => value === 'pending' ? 'all' : 'pending')
              setSelectedIds([])
              setPage(1)
            }}
          >
            Belum dinilai
          </button>
          <button
            type="button"
            aria-pressed={assessment === 'assessed'}
            onClick={() => {
              setAssessment((value) => value === 'assessed' ? 'all' : 'assessed')
              setSelectedIds([])
              setPage(1)
            }}
          >
            Sudah dinilai
          </button>
          {(q || assessment !== 'all') && (
            <button
              type="button"
              onClick={() => {
                setDraft('')
                setQ('')
                setAssessment('all')
                setSelectedIds([])
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
        {assessment === 'pending' && (
          <form
            className="ws-register-tools"
            aria-label="Penetapan massal aset belum dinilai"
            onSubmit={(event) => {
              event.preventDefault()
              if (!selectedIds.length || !bulkUnit || !bulkCategory) {
                toast.error('Pilih 1–100 aset, unit pemilik, dan kategori aset.')
                return
              }
              if (selectedIds.length > 100) {
                toast.error('Maksimal 100 aset dapat ditetapkan sekaligus.')
                return
              }
              setBulkResult(null)
              setBulkErrors({})
              bulkAssign.mutate()
            }}
          >
            <strong>{selectedIds.length} aset dipilih</strong>
            <small>Pilih maksimal 100 aset berstatus belum dinilai. Pilihan dapat melintasi halaman.</small>
            <label>
              <span className="sr-only">Unit pemilik</span>
              <select aria-label="Unit pemilik untuk penetapan massal" value={bulkUnit} onChange={(event) => setBulkUnit(event.target.value)}>
                <option value="">Pilih unit pemilik</option>
                {lookups.data.unit.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}
              </select>
            </label>
            {bulkErrors.unit_pemilik_id?.[0] && <small role="alert">{bulkErrors.unit_pemilik_id[0]}</small>}
            <label>
              <span className="sr-only">Kategori aset</span>
              <select aria-label="Kategori untuk penetapan massal" value={bulkCategory} onChange={(event) => setBulkCategory(event.target.value)}>
                <option value="">Pilih kategori</option>
                {allowedCategories.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}
              </select>
            </label>
            {bulkErrors.kategori_id?.[0] && <small role="alert">{bulkErrors.kategori_id[0]}</small>}
            <button type="submit" className="primary" disabled={bulkAssign.isPending || !selectedIds.length}>
              {bulkAssign.isPending ? 'Menetapkan...' : 'Tetapkan aset terpilih'}
            </button>
            {selectedIds.length > 0 && <button type="button" onClick={() => setSelectedIds([])} disabled={bulkAssign.isPending}>Batal pilih</button>}
          </form>
        )}
        {bulkResult && (
          <div className="ws-panel" role="status" aria-live="polite">
            <strong>Hasil penetapan: {bulkResult.berhasil} berhasil, {bulkResult.gagal} gagal.</strong>
            {bulkResult.masalah.length > 0 && (
              <ul>{bulkResult.masalah.map((item) => <li key={item.id}>Aset #{item.id}: {item.pesan}</li>)}</ul>
            )}
          </div>
        )}
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
                    <th>
                      {assessment === 'pending' && <input
                        type="checkbox"
                        aria-label="Pilih semua aset belum dinilai di halaman ini"
                        checked={pendingRows.length > 0 && pendingRows.every((row) => selectedIds.includes(row.id))}
                        onChange={(event) => {
                          const ids = pendingRows.map((row) => row.id)
                          setSelectedIds((current) => {
                            if (event.target.checked) {
                              const combined = [...new Set([...current, ...ids])]
                              if (combined.length > 100) toast.info('Maksimal 100 aset dapat dipilih sekaligus. Sebagian aset di halaman ini belum dipilih.')
                              return combined.slice(0, 100)
                            }
                            return current.filter((id) => !ids.includes(id))
                          })
                        }}
                      />}
                    </th>
                    <th>Kode aset</th>
                    <th>Nama</th>
                    <th>Sumber</th>
                    <th>Unit pemilik</th>
                    <th>Kategori</th>
                    <th>Kondisi</th>
                    <th>Penilaian</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {list.data.data.map((row) => {
                    const needsReview = isAssetPendingAssessment(row)
                    return (
                      <tr key={row.id}>
                        <td>{needsReview && assessment === 'pending' && <input type="checkbox" aria-label={'Pilih aset ' + row.id} checked={selectedIds.includes(row.id)} onChange={(event) => toggleAsset(row.id, event.target.checked)} />}</td>
                        <td>{str(row.kode_aset) || '-'}</td>
                        <td>
                          <button type="button" className="ws-cell-link" onClick={() => setSelected(row)}>
                            {str(row.nama) || '#' + row.id}
                          </button>
                        </td>
                        <td>{labelOf(lookups.data.sumber, row.sumber_id)}</td>
                        <td>{labelOf(lookups.data.unit, row.unit_pemilik_id)}</td>
                        <td>{labelOf(lookups.data.kategori, row.kategori_id)}</td>
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
  const details = Object.entries(row).filter(
    ([key]) => !['data_mentah', 'hash_data'].includes(key),
  )
  const detailValue = (key: string, value: unknown) => {
    if (key === 'perlu_ditinjau') {
      return isAssetPendingAssessment({ perlu_ditinjau: value }) ? 'BELUM DINILAI' : 'SUDAH DINILAI'
    }
    if (key === 'unit_pemilik_id') return labelOf(lookups.unit, value)
    if (key === 'kategori_id') return labelOf(lookups.kategori, value)
    if (key === 'klasifikasi_informasi_id') return labelOf(lookups.klasifikasi, value)
    if (key === 'sumber_id') return labelOf(lookups.sumber, value)
    if (value == null || value === '') return '-'
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
  }

  return (
    <div className="ws-detail">
      <details>
        <summary>Seluruh data aset</summary>
        <dl>
          {details.map(([key, value]) => (
            <div key={key}>
              <dt>{key.replaceAll('_', ' ')}</dt>
              <dd>{detailValue(key, value)}</dd>
            </div>
          ))}
        </dl>
      </details>
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
