import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { revealNip } from '../../api/auth'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import { byKodeNama, dateOnly, getOptions, getPage, type Option, type Row } from './api'
import { CalendarX2, Edit3, Plus, RefreshCw, Trash2, UserRound } from 'lucide-react'

type EmployeeForm = {
  nip: string
  nama: string
  email_kontak: string
  jenis_kepegawaian: string
  aktif: boolean
  // Penempatan awal saat tambah pegawai
  unit_id: string
  mulai: string
  utama: boolean
}

type PlacementForm = {
  unit_id: string
  mulai: string
  selesai: string
  utama: boolean
}

const todayStr = () => new Date().toISOString().slice(0, 10)

const blank: EmployeeForm = {
  nip: '',
  nama: '',
  email_kontak: '',
  jenis_kepegawaian: 'PNS',
  aktif: true,
  unit_id: '',
  mulai: todayStr(),
  utama: true,
}

const blankPlacement: PlacementForm = {
  unit_id: '',
  mulai: todayStr(),
  selesai: '',
  utama: true,
}

const stringOf = (value: unknown) => (value == null ? '' : String(value))
const firstError = (errors: Record<string, string[]>, key: string) => errors[key]?.[0]

const JENIS_KEPEGAWAIAN_OPTIONS = [
  { value: 'PNS', label: 'PNS' },
  { value: 'PPPK', label: 'PPPK' },
  { value: 'TNI', label: 'TNI' },
  { value: 'NON_ASN', label: 'Non-ASN / Honorer' },
]

export function KelolaPegawai({ superAdmin = false }: { superAdmin?: boolean }) {
  const client = useQueryClient()
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<Row | null>(null)
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState<Row | null>(null)
  const [revealTarget, setRevealTarget] = useState<Row | null>(null)
  const [nipPassword, setNipPassword] = useState('')
  const [revealedNips, setRevealedNips] = useState<Record<number, string>>({})
  const [form, setForm] = useState<EmployeeForm>(blank)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const units = useQuery({
    queryKey: ['satker', 'pegawai', 'units'],
    queryFn: ({ signal }) => getOptions('/api/unit-organisasi', byKodeNama, signal),
  })

  const list = useQuery({
    queryKey: ['satker', 'pegawai', page],
    queryFn: ({ signal }) =>
      getPage('/api/pegawai', { page, per_page: 10, sort: '-id' }, signal),
  })

  const refresh = () => {
    setRevealedNips({})
    void client.invalidateQueries({ queryKey: ['satker', 'pegawai'] })
    void client.invalidateQueries({ queryKey: ['satker', 'opt', 'pegawai'] })
    void client.invalidateQueries({ queryKey: ['satker', 'users', 'employees'] })
  }

  const [saving, setSaving] = useState(false)

  const handleCreateSubmit = async (event: FormEvent) => {
    event.preventDefault()
    const next: Record<string, string[]> = {}
    if (!form.nama.trim()) next.nama = ['Nama wajib diisi.']
    if (!form.email_kontak.trim()) next.email_kontak = ['Email kontak wajib diisi.']
    if (!form.jenis_kepegawaian) next.jenis_kepegawaian = ['Pilih jenis kepegawaian.']
    if (form.nip.trim() && !/^\d{1,18}$/.test(form.nip.trim())) {
      next.nip = ['NIP hanya boleh berisi 1 sampai 18 digit angka.']
    }
    if (!form.unit_id) next.unit_id = ['Pilih unit penempatan awal di wilayah satker.']
    if (!form.mulai) next.mulai = ['Tanggal mulai penempatan wajib diisi.']

    if (Object.keys(next).length) {
      setErrors(next)
      return
    }

    setErrors({})
    setSaving(true)

    let createdPegawai: Row
    try {
      const response = await api.post<{ data: Row }>('/api/pegawai', {
        nip: form.nip.trim() || null,
        nama: form.nama.trim(),
        email_kontak: form.email_kontak.trim(),
        jenis_kepegawaian: form.jenis_kepegawaian,
      })
      createdPegawai = response.data.data
    } catch (error) {
      setSaving(false)
      const errMap = fieldErrors(error)
      setErrors(errMap)
      toast.error(errorMessage(error))
      return
    }

    // Langkah 2: Buat penempatan awal pegawai
    try {
      await api.post('/api/penempatan-pegawai', {
        pegawai_id: createdPegawai.id,
        unit_id: Number(form.unit_id),
        mulai: form.mulai,
        utama: form.utama,
      })
      toast.success('Pegawai dan penempatan awal berhasil dibuat.')
      setCreating(false)
      setForm(blank)
      refresh()
    } catch (error) {
      toast.error(
        `Pegawai berhasil dibuat, namun penempatan awal gagal: ${errorMessage(error)}. Silakan tambahkan penempatan manual agar pegawai tampil di daftar.`,
      )
      setCreating(false)
      setSelected(createdPegawai)
      refresh()
    } finally {
      setSaving(false)
    }
  }

  const updatePegawai = useMutation({
    mutationFn: async () => {
      if (!selected) return
      const body = {
        ...(form.nip.trim() ? { nip: form.nip.trim() } : {}),
        nama: form.nama.trim(),
        email_kontak: form.email_kontak.trim(),
        jenis_kepegawaian: form.jenis_kepegawaian,
        aktif: form.aktif,
      }
      const response = await api.patch<{ data: Row }>('/api/pegawai/' + selected.id, body)
      return response.data.data
    },
    onSuccess: (updated) => {
      toast.success('Data pegawai berhasil diperbarui.')
      if (updated) setSelected(updated)
      setErrors({})
      refresh()
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(errorMessage(error))
    },
  })

  const handleEditSubmit = (event: FormEvent) => {
    event.preventDefault()
    const next: Record<string, string[]> = {}
    if (!form.nama.trim()) next.nama = ['Nama wajib diisi.']
    if (!form.email_kontak.trim()) next.email_kontak = ['Email kontak wajib diisi.']
    if (!form.jenis_kepegawaian) next.jenis_kepegawaian = ['Pilih jenis kepegawaian.']
    if (form.nip.trim() && !/^\d{1,18}$/.test(form.nip.trim())) {
      next.nip = ['NIP hanya boleh berisi 1 sampai 18 digit angka.']
    }
    if (Object.keys(next).length) {
      setErrors(next)
      return
    }
    setErrors({})
    updatePegawai.mutate()
  }

  const removePegawai = useMutation({
    mutationFn: async () => {
      if (deleting) await api.delete('/api/pegawai/' + deleting.id)
    },
    onSuccess: () => {
      toast.success('Pegawai berhasil dihapus.')
      setDeleting(null)
      refresh()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const reveal = useMutation({
    mutationFn: async () => {
      if (!revealTarget) return ''
      return revealNip(revealTarget.id, nipPassword)
    },
    onSuccess: (nip) => {
      if (revealTarget) setRevealedNips((current) => ({ ...current, [revealTarget.id]: nip }))
      setRevealTarget(null)
      setNipPassword('')
      setErrors({})
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(errorMessage(error))
    },
  })

  const startNew = () => {
    setSelected(null)
    const defaultUnitId = units.data?.length === 1 ? String(units.data[0].id) : ''
    setForm({
      ...blank,
      unit_id: defaultUnitId,
      mulai: todayStr(),
    })
    setErrors({})
    setCreating(true)
  }

  const startEdit = (row: Row) => {
    setCreating(false)
    setSelected(row)
    setErrors({})
    setForm({
      nip: '',
      nama: stringOf(row.nama),
      email_kontak: stringOf(row.email_kontak),
      jenis_kepegawaian: stringOf(row.jenis_kepegawaian) || 'PNS',
      aktif: row.aktif === true || row.aktif === 1,
      unit_id: '',
      mulai: todayStr(),
      utama: true,
    })
  }

  const close = () => {
    setCreating(false)
    setSelected(null)
  }

  const data = list.data

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">{superAdmin ? 'Organisasi' : 'Pengelolaan satker'}</span>
          <h1>Kelola pegawai &amp; penempatan</h1>
          <p>
            {superAdmin
              ? 'Kelola data pegawai dan riwayat penempatan pada seluruh unit organisasi.'
              : 'Kelola identitas pegawai dan riwayat penempatannya pada unit dalam wilayah satker Anda.'}
          </p>
        </div>
        <button className="primary" onClick={startNew}>
          <Plus size={16} /> Tambah pegawai
        </button>
      </div>

      <section className="ws-panel">
        <div className="ws-register-tools">
          <strong>{superAdmin ? 'Seluruh pegawai' : 'Daftar pegawai satker'}</strong>
          <button
            className="ws-refresh"
            aria-label="Perbarui pegawai"
            disabled={list.isFetching}
            onClick={() => void list.refetch()}
          >
            <RefreshCw size={16} />
          </button>
        </div>

        {list.isPending ? (
          <div className="ws-loading" role="status">
            Memuat pegawai...
          </div>
        ) : list.isError ? (
          <div className="ws-empty" role="alert">
            <strong>Daftar pegawai belum dapat dimuat</strong>
            <p>{errorMessage(list.error)}</p>
            <button onClick={() => void list.refetch()}>Coba lagi</button>
          </div>
        ) : !data?.data.length ? (
          <div className="ws-empty">
            <UserRound size={28} />
            <strong>Belum ada pegawai</strong>
            <p>
              {superAdmin
                ? 'Tambahkan pegawai baru beserta penempatan aktifnya.'
                : 'Tambahkan pegawai baru beserta penempatan aktifnya di wilayah satker.'}
            </p>
          </div>
        ) : (
          <>
            <div className="ws-table-wrap">
              <table className="ws-table">
                <thead>
                  <tr>
                    <th>Nama</th>
                    <th>NIP (tersensor)</th>
                    <th>Email kontak</th>
                    <th>Jenis</th>
                    <th>Status</th>
                    <th>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {data.data.map((row) => {
                    const active = row.aktif === true || row.aktif === 1
                    return (
                      <tr key={row.id}>
                        <td>{stringOf(row.nama)}</td>
                        <td>
                          {revealedNips[row.id] ?? (stringOf(row.nip) || '-')}
                          {row.nip_tersedia === true && (
                            <button
                              type="button"
                              className="ws-row-open"
                              aria-label={revealedNips[row.id] ? 'Sensor NIP' : 'Lihat NIP'}
                              onClick={() => {
                                if (revealedNips[row.id]) {
                                  setRevealedNips((current) => {
                                    const next = { ...current }
                                    delete next[row.id]
                                    return next
                                  })
                                } else {
                                  setRevealTarget(row)
                                  setNipPassword('')
                                  setErrors({})
                                }
                              }}
                            >
                              {revealedNips[row.id] ? 'Sensor' : 'Lihat'}
                            </button>
                          )}
                        </td>
                        <td>{stringOf(row.email_kontak) || '-'}</td>
                        <td>{stringOf(row.jenis_kepegawaian) || '-'}</td>
                        <td>
                          <span className={'ws-badge ' + (active ? 'success' : 'neutral')}>
                            <span />
                            {active ? 'AKTIF' : 'NONAKTIF'}
                          </span>
                        </td>
                        <td>
                          <button
                            className="ws-row-open"
                            aria-label={'Ubah pegawai ' + row.nama}
                            title={'Ubah pegawai ' + row.nama}
                            onClick={() => startEdit(row)}
                          >
                            <Edit3 size={16} />
                          </button>
                          <button
                            className="ws-row-open"
                            aria-label={'Hapus pegawai ' + row.nama}
                            title={'Hapus pegawai ' + row.nama}
                            onClick={() => setDeleting(row)}
                          >
                            <Trash2 size={16} />
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
                {data.meta.from || 0}-{data.meta.to || 0} dari {data.meta.total} pegawai
              </span>
              <div>
                <button
                  disabled={page <= 1}
                  onClick={() => {
                    setRevealedNips({})
                    setPage((value) => value - 1)
                  }}
                >
                  Sebelumnya
                </button>
                <span>
                  {data.meta.current_page} / {data.meta.last_page}
                </span>
                <button
                  disabled={page >= data.meta.last_page}
                  onClick={() => {
                    setRevealedNips({})
                    setPage((value) => value + 1)
                  }}
                >
                  Berikutnya
                </button>
              </div>
            </div>
          </>
        )}
      </section>

      <Drawer
        open={creating}
        onClose={close}
        title="Tambah pegawai &amp; penempatan"
        description={
          superAdmin
            ? 'Pegawai baru wajib langsung diberi penempatan aktif pada salah satu unit organisasi.'
            : 'Pegawai baru wajib langsung diberi penempatan aktif pada salah satu unit di wilayah satker Anda.'
        }
      >
        <form className="ws-profile-form" onSubmit={handleCreateSubmit} noValidate>
          <div className="ws-profile-form-grid">
            <Input
              id="employee-nip"
              label="NIP (opsional, maksimal 18 digit)"
              value={form.nip}
              error={firstError(errors, 'nip')}
              maxLength={18}
              inputMode="numeric"
              disabled={saving}
              onChange={(value) => setForm((old) => ({ ...old, nip: value }))}
            />
            <Input
              id="employee-name"
              label="Nama lengkap"
              value={form.nama}
              error={firstError(errors, 'nama')}
              disabled={saving}
              onChange={(value) => setForm((old) => ({ ...old, nama: value }))}
            />
            <Input
              id="employee-email"
              label="Email kontak"
              type="email"
              value={form.email_kontak}
              error={firstError(errors, 'email_kontak')}
              disabled={saving}
              onChange={(value) => setForm((old) => ({ ...old, email_kontak: value }))}
            />
            <div className="ws-profile-field">
              <label htmlFor="employee-type">Jenis kepegawaian</label>
              <select
                id="employee-type"
                value={form.jenis_kepegawaian}
                disabled={saving}
                onChange={(event) =>
                  setForm((old) => ({ ...old, jenis_kepegawaian: event.target.value }))
                }
              >
                {JENIS_KEPEGAWAIAN_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
              {firstError(errors, 'jenis_kepegawaian') && (
                <small role="alert">{firstError(errors, 'jenis_kepegawaian')}</small>
              )}
            </div>

            <div className="ws-profile-field" style={{ gridColumn: '1 / -1', marginTop: 12 }}>
              <h3 style={{ margin: 0, fontSize: '0.95rem', fontWeight: 600 }}>
                Penempatan Awal (Wilayah Satker)
              </h3>
            </div>

            <div className="ws-profile-field">
              <label htmlFor="placement-unit-initial">Unit penempatan</label>
              <select
                id="placement-unit-initial"
                value={form.unit_id}
                disabled={saving}
                onChange={(event) => setForm((old) => ({ ...old, unit_id: event.target.value }))}
              >
                <option value="">Pilih unit di wilayah</option>
                {(units.data ?? []).map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.label}
                  </option>
                ))}
              </select>
              {firstError(errors, 'unit_id') && (
                <small role="alert">{firstError(errors, 'unit_id')}</small>
              )}
            </div>

            <Input
              id="placement-start-initial"
              label="Tanggal mulai"
              type="date"
              value={form.mulai}
              error={firstError(errors, 'mulai')}
              disabled={saving}
              onChange={(value) => setForm((old) => ({ ...old, mulai: value }))}
            />

            <label style={{ gridColumn: '1 / -1' }}>
              <input
                type="checkbox"
                checked={form.utama}
                disabled={saving}
                onChange={(event) => setForm((old) => ({ ...old, utama: event.target.checked }))}
              />{' '}
              Penempatan utama
            </label>
          </div>

          <div className="ws-profile-actions">
            <button className="primary" type="submit" disabled={saving}>
              {saving ? 'Menyimpan...' : 'Simpan pegawai & penempatan'}
            </button>
          </div>
        </form>
      </Drawer>

      <Drawer
        open={!!selected}
        onClose={close}
        title={'Ubah pegawai: ' + stringOf(selected?.nama)}
        description="Ubah identitas pegawai atau kelola riwayat penempatan unit di bawah."
      >
        {selected && (
          <>
            <form className="ws-profile-form" onSubmit={handleEditSubmit} noValidate>
              <div className="ws-profile-form-grid">
                <Input
                  id="edit-nip"
                  label="NIP baru (kosongkan untuk mempertahankan, maksimal 18 digit)"
                  value={form.nip}
                  error={firstError(errors, 'nip')}
                  maxLength={18}
                  inputMode="numeric"
                  disabled={updatePegawai.isPending}
                  onChange={(value) => setForm((old) => ({ ...old, nip: value }))}
                />
                <Input
                  id="edit-nama"
                  label="Nama lengkap"
                  value={form.nama}
                  error={firstError(errors, 'nama')}
                  disabled={updatePegawai.isPending}
                  onChange={(value) => setForm((old) => ({ ...old, nama: value }))}
                />
                <Input
                  id="edit-email"
                  label="Email kontak"
                  type="email"
                  value={form.email_kontak}
                  error={firstError(errors, 'email_kontak')}
                  disabled={updatePegawai.isPending}
                  onChange={(value) => setForm((old) => ({ ...old, email_kontak: value }))}
                />
                <div className="ws-profile-field">
                  <label htmlFor="edit-type">Jenis kepegawaian</label>
                  <select
                    id="edit-type"
                    value={form.jenis_kepegawaian}
                    disabled={updatePegawai.isPending}
                    onChange={(event) =>
                      setForm((old) => ({ ...old, jenis_kepegawaian: event.target.value }))
                    }
                  >
                    {JENIS_KEPEGAWAIAN_OPTIONS.map((opt) => (
                      <option key={opt.value} value={opt.value}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                  {firstError(errors, 'jenis_kepegawaian') && (
                    <small role="alert">{firstError(errors, 'jenis_kepegawaian')}</small>
                  )}
                </div>
                <label>
                  <input
                    type="checkbox"
                    checked={form.aktif}
                    disabled={updatePegawai.isPending}
                    onChange={(event) =>
                      setForm((old) => ({ ...old, aktif: event.target.checked }))
                    }
                  />{' '}
                  Aktif
                </label>
              </div>
              <div className="ws-profile-actions">
                <button className="primary" type="submit" disabled={updatePegawai.isPending}>
                  {updatePegawai.isPending ? 'Menyimpan...' : 'Simpan perubahan data'}
                </button>
              </div>
            </form>

            <PenempatanPegawai pegawai={selected} units={units.data ?? []} />
          </>
        )}
      </Drawer>

      <Drawer
        open={!!revealTarget}
        onClose={() => {
          setRevealTarget(null)
          setNipPassword('')
          setErrors({})
        }}
        title="Konfirmasi untuk melihat NIP"
        description="Masukkan password akun Anda. NIP hanya ditampilkan setelah password terverifikasi."
      >
        <form
          className="ws-profile-form"
          onSubmit={(event) => {
            event.preventDefault()
            if (!nipPassword) {
              setErrors({ password: ['Masukkan password akun Anda.'] })
              return
            }
            setErrors({})
            reveal.mutate()
          }}
          noValidate
        >
          <Input
            id="reveal-nip-password"
            label="Password akun Anda"
            type="password"
            value={nipPassword}
            error={firstError(errors, 'password')}
            disabled={reveal.isPending}
            onChange={setNipPassword}
          />
          <div className="ws-profile-actions">
            <button className="primary" disabled={reveal.isPending}>
              {reveal.isPending ? 'Memverifikasi...' : 'Verifikasi & lihat NIP'}
            </button>
          </div>
        </form>
      </Drawer>

      <ConfirmDialog
        open={!!deleting}
        onClose={() => setDeleting(null)}
        title="Hapus pegawai permanen?"
        description={[
          `Pegawai ${stringOf(deleting?.nama)} akan dihapus permanen.`,
          'Penghapusan ditolak jika pegawai masih memiliki penempatan atau dirujuk data operasional.',
        ]}
        confirmLabel="Hapus pegawai"
        pending={removePegawai.isPending}
        onConfirm={() => removePegawai.mutateAsync()}
      />
    </>
  )
}

function Input({
  id,
  label,
  value,
  error,
  type = 'text',
  disabled,
  maxLength,
  inputMode,
  onChange,
}: {
  id: string
  label: string
  value: string
  error?: string
  type?: string
  disabled?: boolean
  maxLength?: number
  inputMode?: 'numeric' | 'text'
  onChange: (value: string) => void
}) {
  return (
    <div className="ws-profile-field">
      <label htmlFor={id}>{label}</label>
      <input
        id={id}
        type={type}
        value={value}
        disabled={disabled}
        maxLength={maxLength}
        inputMode={inputMode}
        onChange={(event) => onChange(event.target.value)}
      />
      {error && <small role="alert">{error}</small>}
    </div>
  )
}

function PenempatanPegawai({ pegawai, units }: { pegawai: Row; units: Option[] }) {
  const client = useQueryClient()
  const [editing, setEditing] = useState<Row | null>(null)
  const [ending, setEnding] = useState<Row | null>(null)
  const [endingDate, setEndingDate] = useState(todayStr())
  const [form, setForm] = useState<PlacementForm>(blankPlacement)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const list = useQuery({
    queryKey: ['satker', 'pegawai', pegawai.id, 'penempatan'],
    queryFn: ({ signal }) =>
      getPage(
        '/api/penempatan-pegawai',
        { per_page: 100, sort: '-id', filter: { pegawai_id: pegawai.id } },
        signal,
      ),
  })

  const save = useMutation({
    mutationFn: async () => {
      const body = {
        pegawai_id: pegawai.id,
        unit_id: Number(form.unit_id),
        mulai: form.mulai,
        selesai: form.selesai || null,
        utama: form.utama,
      }
      if (editing) await api.patch('/api/penempatan-pegawai/' + editing.id, body)
      else await api.post('/api/penempatan-pegawai', body)
    },
    onSuccess: () => {
      toast.success(editing ? 'Penempatan diperbarui.' : 'Penempatan ditambahkan.')
      setEditing(null)
      const defaultUnitId = units.length === 1 ? String(units[0].id) : ''
      setForm({ ...blankPlacement, unit_id: defaultUnitId })
      setErrors({})
      void client.invalidateQueries({ queryKey: ['satker', 'pegawai', pegawai.id, 'penempatan'] })
      void client.invalidateQueries({ queryKey: ['satker', 'pegawai'] })
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(errorMessage(error))
    },
  })

  const endPlacement = useMutation({
    mutationFn: async () => {
      if (!ending) return
      await api.patch('/api/penempatan-pegawai/' + ending.id, {
        selesai: endingDate,
      })
    },
    onSuccess: () => {
      toast.success('Penempatan pegawai berhasil diakhiri.')
      setEnding(null)
      void client.invalidateQueries({ queryKey: ['satker', 'pegawai', pegawai.id, 'penempatan'] })
      void client.invalidateQueries({ queryKey: ['satker', 'pegawai'] })
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const start = (row?: Row) => {
    setEditing(row ?? null)
    setErrors({})
    if (row) {
      setForm({
        unit_id: stringOf(row.unit_id),
        mulai: dateOnly(row.mulai),
        selesai: dateOnly(row.selesai),
        utama: row.utama === true || row.utama === 1,
      })
    } else {
      const defaultUnitId = units.length === 1 ? String(units[0].id) : ''
      setForm({ ...blankPlacement, unit_id: defaultUnitId, mulai: todayStr() })
    }
  }

  const submit = (event: FormEvent) => {
    event.preventDefault()
    const missing: Record<string, string[]> = {}
    if (!form.unit_id) missing.unit_id = ['Pilih unit penempatan.']
    if (!form.mulai) missing.mulai = ['Tanggal mulai penempatan wajib diisi.']
    if (Object.keys(missing).length) {
      setErrors(missing)
      return
    }
    setErrors({})
    save.mutate()
  }

  return (
    <section className="ws-panel" style={{ marginTop: 20 }}>
      <div className="ws-register-tools">
        <h3>Riwayat penempatan unit</h3>
        <button type="button" onClick={() => start()}>
          <Plus size={15} /> Tambah penempatan
        </button>
      </div>

      {list.isPending ? (
        <p role="status">Memuat penempatan...</p>
      ) : list.isError ? (
        <p role="alert">{errorMessage(list.error)}</p>
      ) : list.data.data.length === 0 ? (
        <p>Belum ada riwayat penempatan unit.</p>
      ) : (
        <div className="ws-table-wrap">
          <table className="ws-table">
            <thead>
              <tr>
                <th>Unit</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Utama</th>
                <th>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {list.data.data.map((row) => {
                const isFinished = !!row.selesai
                return (
                  <tr key={row.id}>
                    <td>
                      {units.find((unit) => unit.id === Number(row.unit_id))?.label ??
                        '#' + row.unit_id}
                    </td>
                    <td>{dateOnly(row.mulai)}</td>
                    <td>{dateOnly(row.selesai) || '-'}</td>
                    <td>{row.utama ? 'Ya' : 'Tidak'}</td>
                    <td>
                      <div style={{ display: 'inline-flex', gap: 6, alignItems: 'center' }}>
                        <button
                          type="button"
                          className="ws-row-open"
                          aria-label={'Ubah penempatan ' + row.id}
                          title={'Ubah penempatan'}
                          onClick={() => start(row)}
                        >
                          <Edit3 size={15} />
                        </button>
                        {!isFinished && (
                          <button
                            type="button"
                            className="ws-row-open"
                            aria-label={'Akhiri penempatan ' + row.id}
                            title={'Akhiri penempatan'}
                            style={{ color: '#b91c2c' }}
                            onClick={() => {
                              setEnding(row)
                              setEndingDate(todayStr())
                            }}
                          >
                            <CalendarX2 size={15} />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      <form className="ws-profile-form" onSubmit={submit} noValidate style={{ marginTop: 12 }}>
        <h4>{editing ? 'Ubah penempatan #' + editing.id : 'Tambah penempatan baru'}</h4>
        <div className="ws-profile-form-grid">
          <div className="ws-profile-field">
            <label htmlFor="placement-unit">Unit penempatan</label>
            <select
              id="placement-unit"
              value={form.unit_id}
              disabled={save.isPending}
              onChange={(event) => setForm((old) => ({ ...old, unit_id: event.target.value }))}
            >
              <option value="">Pilih unit</option>
              {units.map((unit) => (
                <option key={unit.id} value={unit.id}>
                  {unit.label}
                </option>
              ))}
            </select>
            {firstError(errors, 'unit_id') && (
              <small role="alert">{firstError(errors, 'unit_id')}</small>
            )}
          </div>

          <Input
            id="placement-start"
            label="Tanggal mulai"
            type="date"
            value={form.mulai}
            error={firstError(errors, 'mulai')}
            disabled={save.isPending}
            onChange={(value) => setForm((old) => ({ ...old, mulai: value }))}
          />

          <Input
            id="placement-end"
            label="Tanggal selesai (opsional)"
            type="date"
            value={form.selesai}
            error={firstError(errors, 'selesai')}
            disabled={save.isPending}
            onChange={(value) => setForm((old) => ({ ...old, selesai: value }))}
          />

          <label>
            <input
              type="checkbox"
              checked={form.utama}
              disabled={save.isPending}
              onChange={(event) => setForm((old) => ({ ...old, utama: event.target.checked }))}
            />{' '}
            Penempatan utama
          </label>
        </div>

        <div className="ws-profile-actions">
          <button className="primary" disabled={save.isPending}>
            {save.isPending
              ? 'Menyimpan...'
              : editing
                ? 'Simpan penempatan'
                : 'Tambah penempatan'}
          </button>
          {editing && (
            <button type="button" onClick={() => start()}>
              Batal ubah
            </button>
          )}
        </div>
      </form>

      <ConfirmDialog
        open={!!ending}
        onClose={() => setEnding(null)}
        title="Akhiri penempatan pegawai?"
        description={[
          `Penempatan pada unit akan diakhiri per tanggal ${endingDate}.`,
          'Pegawai tidak lagi memiliki akses aktif pada unit ini setelah tanggal tersebut.',
        ]}
        confirmLabel="Ya, akhiri penempatan"
        pending={endPlacement.isPending}
        onConfirm={() => endPlacement.mutateAsync()}
      />
    </section>
  )
}
