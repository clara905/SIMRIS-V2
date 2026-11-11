import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Edit3, Plus, Search, Trash2 } from 'lucide-react'
import { useMemo, useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import { byKodeNama, getOptions, getPage, type Option, type Row } from './api'
import { Field, KondisiPill, Pager, Section, SelectBox, StatusPill } from './asetUi'
import { text } from './asetUtils'

const conditions = ['BAIK', 'RUSAK_BIASA', 'RUSAK_BERAT', 'TIDAK_DIKETAHUI']
const statuses = ['AKTIF', 'NONAKTIF']
const humanLabel = (value: string) => value.charAt(0) + value.slice(1).toLowerCase().replaceAll('_', ' ')

type TreeOption = { id: number; parent_id: number | null; label: string }
type UnitOption = Option & { nama: string }
type AssetOptions = {
  units: Array<{ id: number; parent_id: number | null; kode: string; nama: string }>
  categories: Array<{ id: number; parent_id: number | null; kode: string; nama: string }>
}
type AssetForm = {
  unit: string
  kode: string
  nama: string
  kategori: string
  klasifikasi: string
  deskripsi: string
  merk: string
  nomorSeri: string
  jumlah: string
  kondisi: string
  lokasi: string
  tanggalPengadaan: string
  status: string
}

const emptyForm: AssetForm = {
  unit: '',
  kode: '',
  nama: '',
  kategori: '',
  klasifikasi: '',
  deskripsi: '',
  merk: '',
  nomorSeri: '',
  jumlah: '1',
  kondisi: '',
  lokasi: '',
  tanggalPengadaan: '',
  status: 'AKTIF',
}

function treeOptions(items: AssetOptions['categories']): TreeOption[] {
  const byId = new Map(items.map((item) => [item.id, item]))

  return items.map((item) => {
    const path = [item.nama || item.kode]
    const visited = new Set([item.id])
    let parent = item.parent_id == null ? undefined : byId.get(item.parent_id)
    while (parent && !visited.has(parent.id)) {
      visited.add(parent.id)
      path.unshift(parent.nama || parent.kode)
      parent = parent.parent_id == null ? undefined : byId.get(parent.parent_id)
    }

    return { id: item.id, parent_id: item.parent_id, label: path.join(' › ') }
  })
}

export function KasubAsetCrud() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [draft, setDraft] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [selected, setSelected] = useState<Row | null>(null)
  const [removing, setRemoving] = useState<Row | null>(null)
  const client = useQueryClient()

  const options = useQuery({
    queryKey: ['kasub', 'aset', 'pilihan'],
    queryFn: async ({ signal }) => {
      const response = await api.get<{ data: AssetOptions }>('/api/aset/pilihan', { signal })
      return response.data.data
    },
    staleTime: 300_000,
  })
  const classifications = useQuery({
    queryKey: ['kasub', 'aset', 'klasifikasi'],
    queryFn: ({ signal }) => getOptions('/api/klasifikasi-informasi', byKodeNama, signal),
    staleTime: 300_000,
  })
  const list = useQuery({
    queryKey: ['kasub', 'aset', page, search],
    queryFn: ({ signal }) => getPage('/api/aset', {
      page,
      sort: '-id',
      filter: { sumber: 'lokal' },
      ...(search ? { q: search } : {}),
    }, signal),
  })
  const categories = useMemo(
    () => treeOptions((options.data?.categories ?? []).filter((category) => category.kode !== 'BELUM_DIKATEGORIKAN')),
    [options.data?.categories],
  )
  const units: UnitOption[] = useMemo(
    () => (options.data?.units ?? []).map((unit) => ({
      id: unit.id,
      nama: unit.nama,
      label: [unit.kode, unit.nama].filter(Boolean).join(' - '),
    })),
    [options.data?.units],
  )
  const openNew = () => {
    setSelected(null)
    setFormOpen(true)
  }
  const openEdit = (row: Row) => {
    setSelected(row)
    setFormOpen(true)
  }
  const closeForm = () => {
    setFormOpen(false)
    setSelected(null)
  }
  const refreshList = () => void client.invalidateQueries({ queryKey: ['kasub', 'aset'] })

  const remove = useMutation({
    mutationFn: (id: number) => api.delete('/api/aset/' + id),
    onSuccess: () => {
      toast.success('Inventaris aset dihapus.')
      setRemoving(null)
      refreshList()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">Pengelolaan unit</span>
          <h1>Inventaris aset</h1>
          <p>Kelola aset lokal pada unit dalam lingkup Kasub. Aset hasil sinkronisasi eksternal tetap terlindungi.</p>
        </div>
        <button type="button" className="primary" onClick={openNew} disabled={options.isPending || !options.data?.units.length}>
          <Plus size={16} /> Tambah aset
        </button>
      </div>

      {options.isError && <p role="alert">{errorMessage(options.error)}</p>}
      {!options.isPending && !options.isError && !options.data?.units.length && (
        <p role="status">Tidak ada unit aktif dalam lingkup Kasub.</p>
      )}

      <section className="as-card">
        <div className="as-toolbar">
          <form
            className="as-search"
            onSubmit={(event) => {
              event.preventDefault()
              setSearch(draft.trim())
              setPage(1)
            }}
          >
            <Search size={17} />
            <input
              aria-label="Cari inventaris lokal"
              placeholder="Cari kode atau nama aset…"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
            />
            <button type="submit" className="as-btn">Cari</button>
          </form>
          {search && (
            <button type="button" className="as-btn" onClick={() => { setDraft(''); setSearch(''); setPage(1) }}>
              Reset
            </button>
          )}
        </div>

        {classifications.isError && (
          <div className="as-banner" role="alert">
            Daftar klasifikasi belum dapat dimuat. Anda masih dapat mengisi data aset tanpa klasifikasi.
          </div>
        )}

        {list.isPending ? (
          <div className="as-state" role="status">Memuat inventaris aset…</div>
        ) : list.isError ? (
          <div className="as-state" role="alert">
            <strong>Data belum dapat dimuat</strong>
            <p>{errorMessage(list.error)}</p>
            <button type="button" className="as-btn" onClick={() => void list.refetch()}>Coba lagi</button>
          </div>
        ) : !list.data.data.length ? (
          <div className="as-state">
            <strong>{search ? 'Aset tidak ditemukan' : 'Belum ada inventaris lokal'}</strong>
            <p>{search ? 'Coba kata kunci lain.' : 'Klik “Tambah aset” untuk mencatat aset pertama.'}</p>
          </div>
        ) : (
          <>
            <div className="as-table-wrap">
              <table className="as-table">
                <caption className="sr-only">Inventaris aset lokal dalam lingkup Kasub</caption>
                <thead>
                  <tr>
                    <th>Aset</th>
                    <th>Unit pemilik</th>
                    <th>Lokasi</th>
                    <th>Kondisi</th>
                    <th>Status</th>
                    <th><span className="sr-only">Aksi</span></th>
                  </tr>
                </thead>
                <tbody>
                  {list.data.data.map((row) => (
                    <tr key={row.id}>
                      <td data-label="Aset">
                        <button type="button" className="as-cell-main" onClick={() => openEdit(row)}>
                          {text(row.nama) || '#' + row.id}
                        </button>
                        <div className="as-sub">{text(row.kode_aset) || '-'}</div>
                      </td>
                      <td data-label="Unit pemilik">{units.find((unit) => unit.id === Number(row.unit_pemilik_id))?.nama ?? '-'}</td>
                      <td data-label="Lokasi">{text(row.lokasi) || '-'}</td>
                      <td data-label="Kondisi"><KondisiPill value={row.kondisi} /></td>
                      <td data-label="Status"><StatusPill value={row.status} /></td>
                      <td className="as-actions-cell">
                        <button type="button" className="as-icon-btn" title="Ubah" aria-label={'Ubah aset ' + row.id} onClick={() => openEdit(row)}>
                          <Edit3 size={16} />
                        </button>
                        <button type="button" className="as-icon-btn is-danger" title="Hapus" aria-label={'Hapus aset ' + row.id} onClick={() => setRemoving(row)}>
                          <Trash2 size={16} />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pager meta={list.data.meta} onPage={setPage} />
          </>
        )}
      </section>

      <Drawer
        open={formOpen}
        onClose={closeForm}
        title={selected ? 'Ubah inventaris aset' : 'Tambah inventaris aset'}
        description="Isi data aset milik unit dalam lingkup Kasub."
      >
        {formOpen && options.data && (
          <AsetForm
            key={selected?.id ?? 'new'}
            row={selected}
            units={units}
            categories={categories}
            classifications={classifications.data ?? []}
            onSaved={() => { closeForm(); refreshList() }}
          />
        )}
      </Drawer>
      <ConfirmDialog
        open={!!removing}
        onClose={() => setRemoving(null)}
        title="Hapus inventaris aset"
        description={['Inventaris lokal ini akan dihapus. Aset yang sudah dipakai pada asesmen atau penugasan tidak dapat dihapus.']}
        confirmLabel="Ya, hapus"
        pending={remove.isPending}
        onConfirm={() => { if (removing) remove.mutate(removing.id) }}
      />
    </>
  )
}

function AsetForm({
  row,
  units,
  categories,
  classifications,
  onSaved,
}: {
  row: Row | null
  units: UnitOption[]
  categories: TreeOption[]
  classifications: Option[]
  onSaved: () => void
}) {
  const singleUnit = units.length === 1 ? units[0] : null
  const [form, setForm] = useState<AssetForm>(() => row ? {
    unit: text(row.unit_pemilik_id),
    kode: text(row.kode_aset),
    nama: text(row.nama),
    kategori: text(row.kategori_id),
    klasifikasi: text(row.klasifikasi_informasi_id),
    deskripsi: text(row.deskripsi),
    merk: text(row.merk),
    nomorSeri: text(row.nomor_seri),
    jumlah: text(row.jumlah),
    kondisi: text(row.kondisi),
    lokasi: text(row.lokasi),
    tanggalPengadaan: text(row.tanggal_pengadaan).slice(0, 10),
    status: text(row.status),
  } : {
    ...emptyForm,
    unit: singleUnit ? String(singleUnit.id) : '',
    lokasi: singleUnit?.nama ?? '',
  })
  // Lokasi mengikuti nama unit pemilik selama belum diubah manual.
  const [lokasiAuto, setLokasiAuto] = useState(!row || !text(row.lokasi))
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const client = useQueryClient()

  const save = useMutation({
    mutationFn: (body: Record<string, unknown>) => row
      ? api.patch('/api/aset/' + row.id, body)
      : api.post('/api/aset', body),
    onSuccess: () => {
      toast.success(row ? 'Inventaris aset diperbarui.' : 'Inventaris aset ditambahkan.')
      void client.invalidateQueries({ queryKey: ['kasub', 'aset'] })
      onSaved()
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(errorMessage(error))
    },
  })
  const firstError = (key: string) => errors[key]?.[0]
  const set = (key: keyof AssetForm, value: string) => setForm((current) => ({ ...current, [key]: value }))
  const changeUnit = (id: string) => setForm((current) => ({
    ...current,
    unit: id,
    ...(lokasiAuto ? { lokasi: units.find((unit) => String(unit.id) === id)?.nama ?? '' } : {}),
  }))
  const changeLokasi = (value: string) => {
    setLokasiAuto(false)
    set('lokasi', value)
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    setErrors({})
    save.mutate({
      ...(form.kode.trim() ? { kode_aset: form.kode.trim() } : {}),
      unit_pemilik_id: Number(form.unit || (singleUnit ? singleUnit.id : '')),
      nama: form.nama.trim(),
      kategori_id: Number(form.kategori),
      klasifikasi_informasi_id: form.klasifikasi ? Number(form.klasifikasi) : null,
      deskripsi: form.deskripsi,
      merk: form.merk.trim() || null,
      nomor_seri: form.nomorSeri.trim() || null,
      jumlah: Number(form.jumlah),
      kondisi: form.kondisi,
      lokasi: form.lokasi.trim(),
      tanggal_pengadaan: form.tanggalPengadaan || null,
      status: form.status,
    })
  }

  const busy = save.isPending

  return (
    <form className="as-form" onSubmit={submit}>
      <Section title="Identitas aset">
        <div className="as-grid">
          <Field label="Nama aset" required wide error={firstError('nama')}>
            <input required value={form.nama} maxLength={255} disabled={busy} onChange={(e) => set('nama', e.target.value)} />
          </Field>
          <Field label="Unit pemilik" required error={firstError('unit_pemilik_id')}>
            <SelectBox
              required
              value={form.unit}
              disabled={busy || !!singleUnit}
              onChange={changeUnit}
              options={units}
              placeholder="Pilih unit pemilik"
            />
          </Field>
          <Field label="Kode aset" hint="Kosongkan untuk nomor otomatis." error={firstError('kode_aset')}>
            <input value={form.kode} maxLength={255} disabled={busy} onChange={(e) => set('kode', e.target.value)} />
          </Field>
          <Field label="Kategori aset" required error={firstError('kategori_id')}>
            <SelectBox
              required
              value={form.kategori}
              disabled={busy}
              onChange={(v) => set('kategori', v)}
              options={categories}
              placeholder="Pilih kategori"
            />
          </Field>
          <Field label="Klasifikasi informasi" hint="Opsional." error={firstError('klasifikasi_informasi_id')}>
            <SelectBox
              value={form.klasifikasi}
              disabled={busy}
              onChange={(v) => set('klasifikasi', v)}
              options={classifications}
              placeholder="Tanpa klasifikasi"
            />
          </Field>
        </div>
      </Section>

      <Section title="Spesifikasi">
        <div className="as-grid">
          <Field label="Merk" error={firstError('merk')}>
            <input maxLength={255} value={form.merk} disabled={busy} onChange={(e) => set('merk', e.target.value)} />
          </Field>
          <Field label="Nomor seri" error={firstError('nomor_seri')}>
            <input maxLength={255} value={form.nomorSeri} disabled={busy} onChange={(e) => set('nomorSeri', e.target.value)} />
          </Field>
          <Field label="Jumlah" required error={firstError('jumlah')}>
            <input required type="number" min="0" step="1" value={form.jumlah} disabled={busy} onChange={(e) => set('jumlah', e.target.value)} />
          </Field>
          <Field label="Tanggal pengadaan" error={firstError('tanggal_pengadaan')}>
            <input type="date" value={form.tanggalPengadaan} disabled={busy} onChange={(e) => set('tanggalPengadaan', e.target.value)} />
          </Field>
        </div>
      </Section>

      <Section title="Kondisi & lokasi">
        <div className="as-grid">
          <Field label="Kondisi" required error={firstError('kondisi')}>
            <select required value={form.kondisi} disabled={busy} onChange={(e) => set('kondisi', e.target.value)}>
              <option value="">Pilih kondisi</option>
              {conditions.map((condition) => <option key={condition} value={condition}>{humanLabel(condition)}</option>)}
            </select>
          </Field>
          <Field label="Status" required error={firstError('status')}>
            <select required value={form.status} disabled={busy} onChange={(e) => set('status', e.target.value)}>
              <option value="">Pilih status</option>
              {statuses.map((status) => <option key={status} value={status}>{humanLabel(status)}</option>)}
            </select>
          </Field>
          <Field
            label="Lokasi"
            required
            wide
            hint={lokasiAuto ? 'Terisi otomatis dari unit pemilik. Ubah bila asetnya berada di tempat lain.' : undefined}
            error={firstError('lokasi')}
          >
            <input required maxLength={255} value={form.lokasi} disabled={busy} onChange={(e) => changeLokasi(e.target.value)} />
          </Field>
          <Field label="Deskripsi" wide error={firstError('deskripsi')}>
            <textarea rows={3} maxLength={10000} value={form.deskripsi} disabled={busy} onChange={(e) => set('deskripsi', e.target.value)} />
          </Field>
        </div>
      </Section>

      <div className="as-actions">
        <button type="submit" className="primary" disabled={busy || !units.length}>
          {busy ? 'Menyimpan…' : row ? 'Simpan perubahan' : 'Tambah aset'}
        </button>
      </div>
    </form>
  )
}
