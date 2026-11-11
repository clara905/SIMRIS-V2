import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Edit3, Plus, RefreshCw, Search, Trash2 } from 'lucide-react'
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
import { useSession } from '../../api/auth'
import { KasubAsetCrud } from './KasubAsetCrud'
import { Field, KondisiPill, Pager, Pill, Section, SelectBox, StatusPill } from './asetUi'
import { namaSaja, text as str } from './asetUtils'

type Lookups = {
  unit: Option[]
  kategori: Option[]
  klasifikasi: Option[]
  pegawai: Option[]
  sumber: Option[]
}

const labelOf = (options: Option[] | undefined, id: unknown) =>
  options?.find((o) => String(o.id) === str(id))?.label ?? (id == null || id === '' ? '-' : '#' + str(id))

type BulkResult = { berhasil: number; gagal: number; masalah: Array<{ id: number; pesan: string }> }
type AssessmentFilter = 'all' | 'pending' | 'assessed'

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
  const session = useSession()
  const roleCodes = session.data?.roleCodes ?? []
  const isKasubOnly = roleCodes.includes('KASUB') && !roleCodes.some((role) => ['ADMIN_SATKER', 'SUPER_ADMIN'].includes(role))

  return isKasubOnly ? <KasubAsetCrud /> : <SatkerKelolaAset />
}

function SatkerKelolaAset() {
  const [page, setPage] = useState(1)
  const [draft, setDraft] = useState('')
  const [q, setQ] = useState('')
  const [assessment, setAssessment] = useState<AssessmentFilter>('all')
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
  const changeFilter = (value: AssessmentFilter) => {
    setAssessment(value)
    setSelectedIds([])
    setPage(1)
  }
  const showCheckbox = assessment === 'pending'

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">Pengelolaan satker</span>
          <h1>Kelola aset &amp; penugasan</h1>
          <p>Ubah data lokal aset dan tetapkan penanggung jawab. Data sumber tidak dapat diubah.</p>
        </div>
      </div>

      <section className="as-card">
        <div className="as-toolbar">
          <form
            className="as-search"
            onSubmit={(e) => {
              e.preventDefault()
              setQ(draft.trim())
              setPage(1)
            }}
          >
            <Search size={17} />
            <input
              aria-label="Cari aset"
              placeholder="Cari nama, merk, nomor seri, atau nomor inventaris…"
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
            />
            <button type="submit" className="as-btn">Cari</button>
          </form>

          <div className="as-segment" role="group" aria-label="Filter status penilaian">
            <button type="button" aria-pressed={assessment === 'all'} onClick={() => changeFilter('all')}>Semua</button>
            <button type="button" aria-pressed={assessment === 'pending'} onClick={() => changeFilter('pending')}>Belum dinilai</button>
            <button type="button" aria-pressed={assessment === 'assessed'} onClick={() => changeFilter('assessed')}>Sudah dinilai</button>
          </div>

          <span className="as-toolbar-spacer" />
          {q && (
            <button type="button" className="as-btn" onClick={() => { setDraft(''); setQ(''); setPage(1) }}>
              Reset pencarian
            </button>
          )}
          <button
            type="button"
            className="as-icon-btn"
            title="Perbarui data"
            aria-label="Perbarui data"
            disabled={list.isFetching}
            onClick={() => void list.refetch()}
          >
            <RefreshCw size={16} />
          </button>
        </div>

        {showCheckbox && (
          <form
            className="as-bulk"
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
            <select aria-label="Unit pemilik untuk penetapan massal" value={bulkUnit} onChange={(event) => setBulkUnit(event.target.value)}>
              <option value="">Pilih unit pemilik</option>
              {lookups.data.unit.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}
            </select>
            <select aria-label="Kategori untuk penetapan massal" value={bulkCategory} onChange={(event) => setBulkCategory(event.target.value)}>
              <option value="">Pilih kategori</option>
              {allowedCategories.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}
            </select>
            <button type="submit" className="primary" disabled={bulkAssign.isPending || !selectedIds.length}>
              {bulkAssign.isPending ? 'Menetapkan…' : 'Tetapkan aset terpilih'}
            </button>
            {selectedIds.length > 0 && (
              <button type="button" className="as-btn" onClick={() => setSelectedIds([])} disabled={bulkAssign.isPending}>
                Batal pilih
              </button>
            )}
            {(bulkErrors.unit_pemilik_id?.[0] || bulkErrors.kategori_id?.[0]) && (
              <small role="alert" className="as-error">
                {bulkErrors.unit_pemilik_id?.[0] ?? bulkErrors.kategori_id?.[0]}
              </small>
            )}
            <small>Pilih maksimal 100 aset yang belum dinilai. Pilihan dapat melintasi halaman.</small>
          </form>
        )}

        {bulkResult && (
          <div className="as-banner" role="status" aria-live="polite">
            <strong>Hasil penetapan: {bulkResult.berhasil} berhasil, {bulkResult.gagal} gagal.</strong>
            {bulkResult.masalah.length > 0 && (
              <ul>{bulkResult.masalah.map((item) => <li key={item.id}>Aset #{item.id}: {item.pesan}</li>)}</ul>
            )}
          </div>
        )}

        {list.isPending ? (
          <div className="as-state" role="status">Memuat data…</div>
        ) : list.isError ? (
          <div className="as-state" role="alert">
            <strong>Data belum dapat dimuat</strong>
            <p>{errorMessage(list.error)}</p>
            <button type="button" className="as-btn" onClick={() => void list.refetch()}>Coba lagi</button>
          </div>
        ) : !list.data.data.length ? (
          <div className="as-state">
            <Search size={28} />
            <strong>Tidak ada aset yang ditemukan</strong>
            <p>Coba ubah kata kunci atau periksa kembali lingkup satker Anda.</p>
          </div>
        ) : (
          <>
            <div className="as-table-wrap">
              <table className="as-table">
                <caption className="sr-only">Daftar aset satker</caption>
                <thead>
                  <tr>
                    {showCheckbox && (
                      <th className="as-check">
                        <input
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
                        />
                      </th>
                    )}
                    <th>Aset</th>
                    <th>Unit pemilik</th>
                    <th>Kategori</th>
                    <th>Kondisi</th>
                    <th>Penilaian</th>
                    <th><span className="sr-only">Aksi</span></th>
                  </tr>
                </thead>
                <tbody>
                  {list.data.data.map((row) => {
                    const needsReview = isAssetPendingAssessment(row)
                    const unitLabel = labelOf(lookups.data.unit, row.unit_pemilik_id)
                    const kategoriLabel = labelOf(lookups.data.kategori, row.kategori_id)
                    return (
                      <tr key={row.id}>
                        {showCheckbox && (
                          <td className="as-check">
                            {needsReview && (
                              <input
                                type="checkbox"
                                aria-label={'Pilih aset ' + row.id}
                                checked={selectedIds.includes(row.id)}
                                onChange={(event) => toggleAsset(row.id, event.target.checked)}
                              />
                            )}
                          </td>
                        )}
                        <td data-label="Aset">
                          <button type="button" className="as-cell-main" onClick={() => setSelected(row)}>
                            {str(row.nama) || '#' + row.id}
                          </button>
                          <div className="as-sub">
                            {str(row.kode_aset) || '-'} · {labelOf(lookups.data.sumber, row.sumber_id)}
                          </div>
                        </td>
                        <td data-label="Unit pemilik" title={unitLabel}>{namaSaja(unitLabel)}</td>
                        <td data-label="Kategori" title={kategoriLabel}>{namaSaja(kategoriLabel)}</td>
                        <td data-label="Kondisi"><KondisiPill value={row.kondisi} /></td>
                        <td data-label="Penilaian">
                          <Pill tone={needsReview ? 'warn' : 'ok'}>{needsReview ? 'Belum dinilai' : 'Sudah dinilai'}</Pill>
                        </td>
                        <td className="as-actions-cell">
                          <button
                            type="button"
                            className="as-icon-btn"
                            title="Kelola aset"
                            aria-label={'Kelola aset ' + row.id}
                            onClick={() => setSelected(row)}
                          >
                            <Edit3 size={16} />
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            <Pager meta={list.data.meta} onPage={setPage} />
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
  const needsReview = isAssetPendingAssessment(row)
  const details = Object.entries(row).filter(
    ([key]) => !['data_mentah', 'hash_data'].includes(key),
  )
  const detailValue = (key: string, value: unknown) => {
    if (key === 'perlu_ditinjau') {
      return isAssetPendingAssessment({ perlu_ditinjau: value }) ? 'Belum dinilai' : 'Sudah dinilai'
    }
    if (key === 'unit_pemilik_id') return labelOf(lookups.unit, value)
    if (key === 'kategori_id') return labelOf(lookups.kategori, value)
    if (key === 'klasifikasi_informasi_id') return labelOf(lookups.klasifikasi, value)
    if (key === 'sumber_id') return labelOf(lookups.sumber, value)
    if (value == null || value === '') return '-'
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
  }
  const facts: Array<[string, string]> = [
    ['Kode aset', str(row.kode_aset) || '-'],
    ['Sumber', labelOf(lookups.sumber, row.sumber_id)],
    ['Merk', str(row.merk) || '-'],
    ['Nomor seri', str(row.nomor_seri) || '-'],
    ['Jumlah', str(row.jumlah) || '-'],
    ['Tanggal pengadaan', str(row.tanggal_pengadaan).slice(0, 10) || '-'],
  ]

  return (
    <div className="as-stack">
      <div className="as-summary">
        <h2>{str(row.nama) || 'Aset #' + row.id}</h2>
        <div className="as-pills">
          <KondisiPill value={row.kondisi} />
          <StatusPill value={row.status} />
          <Pill tone={needsReview ? 'warn' : 'ok'}>{needsReview ? 'Belum dinilai' : 'Sudah dinilai'}</Pill>
        </div>
      </div>

      <Section title="Informasi aset">
        <dl className="as-facts">
          {facts.map(([label, value]) => (
            <div key={label}>
              <dt>{label}</dt>
              <dd>{value}</dd>
            </div>
          ))}
        </dl>
        <details className="as-details">
          <summary>Lihat seluruh data</summary>
          <dl>
            {details.map(([key, value]) => (
              <div key={key}>
                <dt>{key.replaceAll('_', ' ')}</dt>
                <dd>{detailValue(key, value)}</dd>
              </div>
            ))}
          </dl>
        </details>
      </Section>

      <Section title="Data lokal aset">
        <form className="as-form" onSubmit={submit} noValidate>
          <div className="as-grid">
            <Field label="Unit pemilik" error={first('unit_pemilik_id')}>
              <SelectBox value={form.unit} onChange={set('unit')} options={lookups.unit} placeholder="Pilih unit" disabled={save.isPending} />
            </Field>
            <Field label="Kategori aset" error={first('kategori_id')}>
              <SelectBox value={form.kategori} onChange={set('kategori')} options={lookups.kategori} placeholder="Pilih kategori" disabled={save.isPending} />
            </Field>
            <Field label="Klasifikasi informasi" error={first('klasifikasi_informasi_id')}>
              <SelectBox value={form.klasifikasi} onChange={set('klasifikasi')} options={lookups.klasifikasi} placeholder="Tanpa klasifikasi" disabled={save.isPending} />
            </Field>
            <Field label="Lokasi" error={first('lokasi')}>
              <input type="text" maxLength={255} value={form.lokasi} disabled={save.isPending} onChange={(e) => set('lokasi')(e.target.value)} />
            </Field>
            <Field label="Deskripsi" wide error={first('deskripsi')}>
              <textarea rows={3} maxLength={10000} value={form.deskripsi} disabled={save.isPending} onChange={(e) => set('deskripsi')(e.target.value)} />
            </Field>
          </div>
          <div className="as-actions">
            <button type="submit" className="primary" disabled={save.isPending}>
              {save.isPending ? 'Menyimpan…' : 'Simpan perubahan'}
            </button>
          </div>
        </form>
      </Section>

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
    <Section title="Penanggung jawab aset">
      {list.isPending ? (
        <p className="as-empty-note" role="status">Memuat penugasan…</p>
      ) : list.isError ? (
        <p className="as-error" role="alert">{errorMessage(list.error)}</p>
      ) : !list.data.data.length ? (
        <p className="as-empty-note">Belum ada penugasan untuk aset ini.</p>
      ) : (
        <ul className="as-assign-list">
          {list.data.data.map((row) => (
            <li key={row.id}>
              <div>
                <strong>{labelOf(pegawai, row.pegawai_id)}</strong>
                <div className="as-sub">
                  {str(row.jenis_tanggung_jawab) || '-'} · {dateOnly(row.mulai) || '-'} s/d {dateOnly(row.selesai) || 'sekarang'}
                </div>
              </div>
              <div>
                <button type="button" className="as-icon-btn" title="Ubah" aria-label={'Ubah penugasan ' + row.id} onClick={() => startEdit(row)}>
                  <Edit3 size={16} />
                </button>
                <button type="button" className="as-icon-btn is-danger" title="Hapus" aria-label={'Hapus penugasan ' + row.id} onClick={() => setRemoving(row)}>
                  <Trash2 size={16} />
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <form className="as-form" onSubmit={submit} noValidate>
        <div className="as-grid">
          <Field label="Pegawai" required wide error={first('pegawai_id')}>
            <SelectBox value={form.pegawai} onChange={(v) => setForm((f) => ({ ...f, pegawai: v }))} options={pegawai} placeholder="Pilih pegawai" disabled={save.isPending} />
          </Field>
          <Field label="Jenis tanggung jawab" required wide error={first('jenis_tanggung_jawab')}>
            <input type="text" maxLength={255} value={form.jenis} disabled={save.isPending} onChange={(e) => setForm((f) => ({ ...f, jenis: e.target.value }))} placeholder="Contoh: Penanggung jawab" />
          </Field>
          <Field label="Mulai" required error={first('mulai')}>
            <input type="date" value={form.mulai} disabled={save.isPending} onChange={(e) => setForm((f) => ({ ...f, mulai: e.target.value }))} />
          </Field>
          <Field label="Selesai" hint="Opsional." error={first('selesai')}>
            <input type="date" value={form.selesai} disabled={save.isPending} onChange={(e) => setForm((f) => ({ ...f, selesai: e.target.value }))} />
          </Field>
        </div>
        <div className="as-actions">
          <button type="submit" className="primary" disabled={save.isPending}>
            {!editing && <Plus size={16} />}
            {save.isPending ? 'Menyimpan…' : editing ? 'Simpan penugasan' : 'Tambah penugasan'}
          </button>
          {editing && (
            <button type="button" className="as-btn" disabled={save.isPending} onClick={cancel}>
              Batal ubah
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
    </Section>
  )
}
