import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import type { Option, Row } from './api'
import { ChevronRight, Edit3, Plus, RefreshCw, Shuffle, Trash2 } from 'lucide-react'

type Unit = Row & {
  parent_id: number | null
  kode: string
  nama: string
  jenis: string
  aktif: boolean
  children?: Unit[]
}

type UnitForm = {
  parent_id: string
  kode: string
  nama: string
  jenis: string
  aktif: boolean
}

const ALLOWED_CHILD_KINDS = ['BIDANG', 'BAGIAN', 'SUBBIDANG', 'SUBBAGIAN']

const emptyForm: UnitForm = {
  parent_id: '',
  kode: '',
  nama: '',
  jenis: '',
  aktif: true,
}

const text = (value: unknown) => (value == null ? '' : String(value))

function flattenTree(nodes: Unit[], depth = 0): Array<{ unit: Unit; depth: number }> {
  return nodes.flatMap((unit) => [
    { unit, depth },
    ...flattenTree(unit.children ?? [], depth + 1),
  ])
}

export function KelolaUnit() {
  const client = useQueryClient()
  const [selected, setSelected] = useState<Unit | null>(null)
  const [creating, setCreating] = useState(false)
  const [moving, setMoving] = useState<Unit | null>(null)
  const [removing, setRemoving] = useState<Unit | null>(null)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [form, setForm] = useState<UnitForm>(emptyForm)
  const [moveParent, setMoveParent] = useState('')

  const tree = useQuery({
    queryKey: ['satker', 'unit', 'tree'],
    queryFn: async ({ signal }) =>
      (await api.get<{ data: Unit[] }>('/api/unit-organisasi/pohon', { signal })).data.data,
  })

  const kinds = useQuery({
    queryKey: ['satker', 'unit', 'kinds'],
    queryFn: async ({ signal }) =>
      (await api.get<{ data: Record<string, string[]> }>('/api/unit-organisasi/jenis', { signal })).data.data,
  })

  const refresh = () => {
    void client.invalidateQueries({ queryKey: ['satker', 'unit'] })
    void client.invalidateQueries({ queryKey: ['satker', 'opt', 'unit'] })
  }

  const save = useMutation({
    mutationFn: async () => {
      const body = {
        kode: form.kode.trim(),
        nama: form.nama.trim(),
        jenis: form.jenis,
        aktif: form.aktif,
      }
      if (creating) {
        await api.post('/api/unit-organisasi', {
          ...body,
          parent_id: Number(form.parent_id),
        })
      } else if (selected) {
        await api.patch('/api/unit-organisasi/' + selected.id, body)
      }
    },
    onSuccess: () => {
      toast.success(creating ? 'Unit anak berhasil dibuat.' : 'Unit berhasil diperbarui.')
      setCreating(false)
      setSelected(null)
      setErrors({})
      refresh()
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(errorMessage(error))
    },
  })

  const move = useMutation({
    mutationFn: async () => {
      if (moving) {
        await api.post('/api/unit-organisasi/' + moving.id + '/pindah-induk', {
          parent_id: Number(moveParent),
        })
      }
    },
    onSuccess: () => {
      toast.success('Induk unit berhasil dipindahkan.')
      setMoving(null)
      refresh()
    },
    onError: (error) => {
      setErrors(fieldErrors(error))
      toast.error(errorMessage(error))
    },
  })

  const deactivate = useMutation({
    mutationFn: async () => {
      if (removing) await api.delete('/api/unit-organisasi/' + removing.id)
    },
    onSuccess: () => {
      toast.success('Unit dinonaktifkan.')
      setRemoving(null)
      refresh()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const rows = tree.data ? flattenTree(tree.data) : []
  const unitOptions: Option[] = rows.map(({ unit }) => ({
    id: unit.id,
    label: `${unit.kode} — ${unit.nama}`,
  }))

  const parentOptions = rows
    .filter(({ unit }) => unit.aktif)
    .map(({ unit }) => ({ id: unit.id, label: `${unit.kode} — ${unit.nama}` }))

  const openCreate = (parent?: Unit) => {
    const defaultParentId = parent
      ? String(parent.id)
      : parentOptions.length === 1
        ? String(parentOptions[0].id)
        : rows[0]?.unit.id
          ? String(rows[0].unit.id)
          : ''
    setForm({
      ...emptyForm,
      parent_id: defaultParentId,
    })
    setErrors({})
    setCreating(true)
    setSelected(null)
  }

  const openEdit = (unit: Unit) => {
    setSelected(unit)
    setCreating(false)
    setErrors({})
    setForm({
      parent_id: text(unit.parent_id),
      kode: unit.kode,
      nama: unit.nama,
      jenis: unit.jenis,
      aktif: unit.aktif,
    })
  }

  const parentType = rows.find((row) => row.unit.id === Number(form.parent_id))?.unit.jenis
  const allowedKindsFromBackend = parentType ? kinds.data?.[parentType] : undefined
  const kindOptions = allowedKindsFromBackend
    ? allowedKindsFromBackend.filter((k) => ALLOWED_CHILD_KINDS.includes(k))
    : ALLOWED_CHILD_KINDS

  const submit = (event: FormEvent) => {
    event.preventDefault()
    const nextErrors: Record<string, string[]> = {}
    if (creating && !form.parent_id) {
      nextErrors.parent_id = ['Pilih unit induk dalam wilayah satker.']
    }
    if (!form.kode.trim()) nextErrors.kode = ['Kode wajib diisi.']
    if (!form.nama.trim()) nextErrors.nama = ['Nama wajib diisi.']
    if (!form.jenis) nextErrors.jenis = ['Pilih jenis unit.']
    else if (!ALLOWED_CHILD_KINDS.includes(form.jenis)) {
      nextErrors.jenis = ['Jenis unit anak hanya boleh BIDANG, BAGIAN, SUBBIDANG, atau SUBBAGIAN.']
    }
    if (Object.keys(nextErrors).length) {
      setErrors(nextErrors)
      return
    }
    setErrors({})
    save.mutate()
  }

  const first = (key: string) => errors[key]?.[0]

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">Pengelolaan satker</span>
          <h1>Kelola unit organisasi</h1>
          <p>
            Kelola struktur unit anak dalam wilayah satker (Bidang, Bagian, Subbidang, atau Subbagian).
            Unit induk satker bersifat tetap (read-only).
          </p>
        </div>
        <button className="primary" onClick={() => openCreate()}>
          <Plus size={16} /> Tambah unit anak
        </button>
      </div>

      <section className="ws-panel">
        <div className="ws-register-tools">
          <strong>Struktur unit satker</strong>
          <button
            className="ws-refresh"
            aria-label="Perbarui struktur unit"
            disabled={tree.isFetching}
            onClick={() => void tree.refetch()}
          >
            <RefreshCw size={16} />
          </button>
        </div>

        {tree.isPending ? (
          <div className="ws-loading" role="status">
            Memuat unit...
          </div>
        ) : tree.isError ? (
          <div className="ws-empty" role="alert">
            <strong>Unit belum dapat dimuat</strong>
            <p>{errorMessage(tree.error)}</p>
            <button onClick={() => void tree.refetch()}>Coba lagi</button>
          </div>
        ) : !rows.length ? (
          <div className="ws-empty">
            <strong>Belum ada unit dalam wilayah</strong>
            <p>Periksa kembali penugasan wilayah akun Admin Satker Anda.</p>
          </div>
        ) : (
          <div className="ws-table-wrap">
            <table className="ws-table">
              <thead>
                <tr>
                  <th>Unit</th>
                  <th>Jenis</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {rows.map(({ unit, depth }) => {
                  const isRootSatker = unit.jenis === 'SATKER' || unit.parent_id === null
                  return (
                    <tr key={unit.id}>
                      <td>
                        <span
                          style={{
                            paddingLeft: depth * 20,
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: 6,
                          }}
                        >
                          <ChevronRight size={14} />
                          <strong>{unit.kode}</strong> — {unit.nama}
                          {isRootSatker && (
                            <small
                              style={{
                                marginLeft: 8,
                                padding: '2px 6px',
                                background: '#f1f5f9',
                                borderRadius: 4,
                                color: '#475569',
                              }}
                            >
                              Induk Satker
                            </small>
                          )}
                        </span>
                      </td>
                      <td>{unit.jenis}</td>
                      <td>
                        <span className={'ws-badge ' + (unit.aktif ? 'success' : 'neutral')}>
                          <span />
                          {unit.aktif ? 'AKTIF' : 'NONAKTIF'}
                        </span>
                      </td>
                      <td>
                        {unit.aktif && (
                          <button
                            className="ws-row-open"
                            aria-label={'Tambah unit anak di bawah ' + unit.nama}
                            title={'Tambah unit anak di bawah ' + unit.nama}
                            onClick={() => openCreate(unit)}
                          >
                            <Plus size={16} />
                          </button>
                        )}
                        {!isRootSatker ? (
                          <>
                            <button
                              className="ws-row-open"
                              aria-label={'Ubah unit ' + unit.nama}
                              title={'Ubah unit ' + unit.nama}
                              onClick={() => openEdit(unit)}
                            >
                              <Edit3 size={16} />
                            </button>
                            <button
                              className="ws-row-open"
                              aria-label={'Pindah induk unit ' + unit.nama}
                              title={'Pindah induk unit ' + unit.nama}
                              onClick={() => {
                                setMoving(unit)
                                setMoveParent(text(unit.parent_id))
                              }}
                            >
                              <Shuffle size={16} />
                            </button>
                            {unit.aktif && (
                              <button
                                className="ws-row-open"
                                aria-label={'Nonaktifkan unit ' + unit.nama}
                                title={'Nonaktifkan unit ' + unit.nama}
                                onClick={() => setRemoving(unit)}
                              >
                                <Trash2 size={16} />
                              </button>
                            )}
                          </>
                        ) : (
                          <span
                            style={{
                              fontSize: '0.8rem',
                              color: 'var(--ws-text-muted, #94a3b8)',
                              marginLeft: 4,
                            }}
                          >
                            Hanya baca
                          </span>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <Drawer
        open={creating || !!selected}
        onClose={() => {
          setCreating(false)
          setSelected(null)
        }}
        title={creating ? 'Tambah unit anak' : 'Ubah unit'}
        description={
          creating
            ? 'Pilih unit induk dalam wilayah satker. Jenis unit hanya Bidang, Bagian, Subbidang, atau Subbagian.'
            : 'Perbarui kode, nama, atau status keaktifan unit anak.'
        }
      >
        <form className="ws-profile-form" onSubmit={submit} noValidate>
          <div className="ws-profile-form-grid">
            {creating && (
              <FieldSelect
                id="unit-parent"
                label="Unit induk"
                emptyLabel="Pilih unit induk"
                value={form.parent_id}
                error={first('parent_id')}
                options={parentOptions}
                onChange={(value) =>
                  setForm((old) => ({ ...old, parent_id: value, jenis: '' }))
                }
              />
            )}
            <Field
              id="unit-kode"
              label="Kode"
              value={form.kode}
              error={first('kode')}
              onChange={(value) => setForm((old) => ({ ...old, kode: value }))}
            />
            <Field
              id="unit-nama"
              label="Nama"
              value={form.nama}
              error={first('nama')}
              onChange={(value) => setForm((old) => ({ ...old, nama: value }))}
            />
            <div className="ws-profile-field">
              <label htmlFor="unit-jenis">Jenis unit</label>
              <select
                id="unit-jenis"
                value={form.jenis}
                onChange={(event) =>
                  setForm((old) => ({ ...old, jenis: event.target.value }))
                }
                disabled={save.isPending}
              >
                <option value="">Pilih jenis unit</option>
                {kindOptions.map((kind) => (
                  <option key={kind} value={kind}>
                    {kind}
                  </option>
                ))}
              </select>
              {first('jenis') && <small role="alert">{first('jenis')}</small>}
            </div>
            {!creating && (
              <label>
                <input
                  type="checkbox"
                  checked={form.aktif}
                  onChange={(event) =>
                    setForm((old) => ({ ...old, aktif: event.target.checked }))
                  }
                />{' '}
                Aktif
              </label>
            )}
          </div>
          <div className="ws-profile-actions">
            <button className="primary" type="submit" disabled={save.isPending}>
              {save.isPending ? 'Menyimpan...' : 'Simpan'}
            </button>
          </div>
        </form>
      </Drawer>

      <Drawer
        open={!!moving}
        onClose={() => {
          setMoving(null)
          setErrors({})
        }}
        title="Pindah induk unit"
        description="Pilih induk baru yang berada dalam wilayah satker Anda."
      >
        <form
          className="ws-profile-form"
          onSubmit={(event) => {
            event.preventDefault()
            if (!moveParent || !moving) {
              setErrors({ parent_id: ['Pilih unit induk baru.'] })
              return
            }
            setErrors({})
            move.mutate()
          }}
        >
          <FieldSelect
            id="move-parent"
            label="Induk baru"
            value={moveParent}
            error={first('parent_id')}
            options={unitOptions.filter((item) => item.id !== moving?.id)}
            onChange={(value) => {
              setMoveParent(value)
              setErrors({})
            }}
          />
          <div className="ws-profile-actions">
            <button className="primary" disabled={move.isPending}>
              {move.isPending ? 'Memindahkan...' : 'Pindahkan'}
            </button>
          </div>
        </form>
      </Drawer>

      <ConfirmDialog
        open={!!removing}
        onClose={() => setRemoving(null)}
        title="Nonaktifkan unit?"
        description={[
          `Unit ${removing?.nama ?? ''} tidak akan aktif lagi.`,
          'Backend akan menolak jika unit masih memiliki anak atau pegawai aktif.',
        ]}
        confirmLabel="Nonaktifkan"
        pending={deactivate.isPending}
        onConfirm={() => deactivate.mutateAsync()}
      />
    </>
  )
}

function Field({
  id,
  label,
  value,
  error,
  onChange,
}: {
  id: string
  label: string
  value: string
  error?: string
  onChange: (value: string) => void
}) {
  return (
    <div className="ws-profile-field">
      <label htmlFor={id}>{label}</label>
      <input id={id} value={value} onChange={(event) => onChange(event.target.value)} />
      {error && <small role="alert">{error}</small>}
    </div>
  )
}

function FieldSelect({
  id,
  label,
  value,
  error,
  options,
  onChange,
  emptyLabel = 'Pilih unit',
}: {
  id: string
  label: string
  value: string
  error?: string
  options: Option[]
  onChange: (value: string) => void
  emptyLabel?: string
}) {
  return (
    <div className="ws-profile-field">
      <label htmlFor={id}>{label}</label>
      <select id={id} value={value} onChange={(event) => onChange(event.target.value)}>
        <option value="">{emptyLabel}</option>
        {options.map((item) => (
          <option key={item.id} value={item.id}>
            {item.label}
          </option>
        ))}
      </select>
      {error && <small role="alert">{error}</small>}
    </div>
  )
}
