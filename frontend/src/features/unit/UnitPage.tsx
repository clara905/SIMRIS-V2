import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Plus, Info, Network } from 'lucide-react'
import { toast } from 'sonner'
import { useUnit, useSaveUnit } from '../../api/unit'
import { usePegawai } from '../../api/pegawai'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { TreeView } from '../../components/TreeView'
import { Drawer } from '../../components/Drawer'
import { RecordForm } from '../../components/RecordForm'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { StatusBadge } from '../../components/StatusBadge'
import { ErrorState, Skeleton } from '../../components/States'
import { isActive, unitPath, unitTree } from '../../lib/records'
import s from '../Records.module.css'
export default function UnitPage() {
  const query = useUnit()
  const employees = usePegawai()
  const save = useSaveUnit()
  const [params] = useSearchParams()
  const [chosen, setSelected] = useState<number | null>(null)
  const [form, setForm] = useState<'new' | 'edit' | 'parent' | null>(null)
  const [parent, setParent] = useState<number | null>(null)
  const [confirm, setConfirm] = useState(false)
  const units = query.data || []
  const missingLeaders = units.filter(
    (u) => u.status === 'aktif' && !u.pimpinan && ['Satker', 'Bidang'].includes(u.jenis),
  )
  const selected =
    chosen ??
    (params.get('filter') === 'tanpa-pimpinan' ? missingLeaders[0]?.id : units[0]?.id) ??
    0
  const unit = units.find((u) => u.id === selected)
  const children = units.filter((u) => u.induk_id === selected)
  const people = (employees.data || []).filter(
    (p) =>
      p.status === 'aktif' &&
      p.penempatan.some((v) => v.unit_id === selected && isActive(v.berakhir, v.mulai)),
  )
  const blocked = children.length > 0 || people.length > 0
  const change = async (status: string) => {
    if (!unit) return
    try {
      await save.mutateAsync({ ...unit, status })
      toast.success('Status unit diperbarui')
      setConfirm(false)
    } catch (err) {
      toast.error(errorMessage(err))
    }
  }
  return (
    <>
      <PageHeader
        title="Unit organisasi"
        description="Susun struktur unit dan pastikan setiap penempatan memiliki tempat."
        actions={
          <button className="primary" onClick={() => setForm('new')}>
            <Plus size={17} />
            Tambah unit
          </button>
        }
      />
      {params.get('filter') === 'tanpa-pimpinan' && (
        <div className={s.notice}>
          <Info size={16} />
          <div>
            {missingLeaders.length} unit belum memiliki pimpinan.
            <div className={s.actions}>
              {missingLeaders.map((u) => (
                <button key={u.id} className="link-button" onClick={() => setSelected(u.id)}>
                  {u.nama}
                </button>
              ))}
            </div>
          </div>
        </div>
      )}
      {query.isPending ? (
        <Skeleton />
      ) : query.isError ? (
        <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
      ) : (
        <div className={s.split}>
          <section className={s.panel}>
            <div className={s.panelHead}>
              <h2>Struktur organisasi</h2>
              <Network size={18} />
            </div>
            <TreeView
              nodes={unitTree(units)}
              selected={selected}
              onSelect={setSelected}
              label="Struktur unit organisasi"
            />
          </section>
          {unit && (
            <section className={s.panel}>
              <div className={s.panelHead}>
                <h2>{unit.nama}</h2>
                <StatusBadge status={unit.status} />
              </div>
              <p className={s.path}>{unitPath(units, unit.id).join(' / ')}</p>
              <dl className={s.meta}>
                <dt>Kode unit</dt>
                <dd>{unit.kode}</dd>
                <dt>Jenis unit</dt>
                <dd>{unit.jenis}</dd>
                <dt>Pimpinan</dt>
                <dd>{unit.pimpinan || 'Belum ditetapkan'}</dd>
                <dt>Pegawai aktif</dt>
                <dd>{people.length} pegawai</dd>
                <dt>Unit anak</dt>
                <dd>{children.length} unit</dd>
              </dl>
              <div className={s.actions}>
                <button onClick={() => setForm('edit')}>Ubah unit</button>
                <button
                  onClick={() => {
                    setParent(unit.induk_id)
                    setForm('parent')
                  }}
                >
                  Pindah induk
                </button>
              </div>
              <section className={s.section}>
                <h3>Unit anak</h3>
                {children.length ? (
                  <ul className={s.list}>
                    {children.map((child) => (
                      <li key={child.id}>
                        <button className="link-button" onClick={() => setSelected(child.id)}>
                          {child.nama}
                        </button>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className={s.muted}>
                    Belum ada unit anak. Tambahkan unit jika struktur memerlukannya.
                  </p>
                )}
              </section>
              <section className={s.section}>
                <h3>Pegawai aktif</h3>
                <ul className={s.list}>
                  {people.map((person) => (
                    <li key={person.id}>
                      <Link to={`/pegawai?pegawai=${person.id}`}>{person.nama}</Link>
                    </li>
                  ))}
                </ul>
                {!people.length && (
                  <p className={s.muted}>Belum ada pegawai aktif pada unit ini.</p>
                )}
              </section>
              <section className={s.section}>
                <h3>Riwayat unit</h3>
                <Link to={`/audit?entitas=Unit%20organisasi&nama=${encodeURIComponent(unit.nama)}`}>
                  Lihat perubahan unit di log audit
                </Link>
              </section>
              <section className={s.section}>
                <button
                  disabled={unit.status === 'aktif' && blocked}
                  aria-describedby="unit-block"
                  onClick={() => setConfirm(true)}
                >
                  {unit.status === 'aktif' ? 'Nonaktifkan unit' : 'Aktifkan unit'}
                </button>
                {blocked && (
                  <div id="unit-block" className={s.notice}>
                    <Info size={16} />
                    <p>
                      Unit memiliki {children.length} anak dan {people.length} pegawai aktif. Pilih
                      unit anak untuk memindahkan induknya atau{' '}
                      <Link to={`/pegawai?unit=${unit.id}`}>pindahkan pegawai</Link> sebelum
                      menonaktifkan unit.
                    </p>
                  </div>
                )}
              </section>
            </section>
          )}
        </div>
      )}
      <Drawer
        open={!!form}
        onClose={() => setForm(null)}
        title={
          form === 'new' ? 'Tambah unit' : form === 'parent' ? 'Pindah induk unit' : 'Ubah unit'
        }
      >
        {form === 'parent' && unit ? (
          <>
            <p>
              Pilih induk baru untuk {unit.nama}. Unit tidak dapat dipindahkan ke dalam turunannya
              sendiri.
            </p>
            <button onClick={() => setParent(null)} aria-pressed={parent === null}>
              Jadikan unit tingkat atas
            </button>
            <TreeView
              nodes={unitTree(units)}
              selected={parent ?? undefined}
              onSelect={setParent}
              label="Pilih induk baru"
            />
            <button
              className="primary"
              disabled={save.isPending || parent === unit.id}
              onClick={async () => {
                try {
                  await save.mutateAsync({ ...unit, induk_id: parent })
                  setForm(null)
                  toast.success('Induk unit diperbarui')
                } catch (err) {
                  toast.error(errorMessage(err))
                }
              }}
            >
              Simpan induk unit
            </button>
          </>
        ) : (
          form && (
            <RecordForm
              key={form}
              fields={[
                { name: 'nama', label: 'Nama unit' },
                { name: 'kode', label: 'Kode unit' },
                {
                  name: 'jenis',
                  label: 'Jenis unit',
                  type: 'select',
                  options: ['Satker', 'Bidang', 'Subbidang'].map((value) => ({
                    value,
                    label: value,
                  })),
                },
                {
                  name: 'induk_id',
                  label: 'Induk unit',
                  type: 'select',
                  required: false,
                  options: units
                    .filter((u) => u.id !== (form === 'edit' ? selected : 0))
                    .map((u) => ({ value: String(u.id), label: u.nama })),
                },
                { name: 'pimpinan', label: 'Kasatker atau Kabid', required: false },
              ]}
              values={
                form === 'edit' && unit
                  ? {
                      nama: unit.nama,
                      kode: unit.kode,
                      jenis: unit.jenis,
                      induk_id: String(unit.induk_id || ''),
                      pimpinan: unit.pimpinan,
                    }
                  : { induk_id: String(selected) }
              }
              onCancel={() => setForm(null)}
              submitLabel={form === 'new' ? 'Tambah unit' : 'Simpan perubahan'}
              onSave={async (v) => {
                const value = await save.mutateAsync({
                  ...(form === 'edit' ? unit : {}),
                  ...v,
                  induk_id: v.induk_id ? Number(v.induk_id) : null,
                })
                setSelected(value.id)
                setForm(null)
                toast.success('Unit disimpan')
              }}
            />
          )
        )}
      </Drawer>
      <ConfirmDialog
        open={confirm}
        onClose={() => setConfirm(false)}
        title="Ubah status unit"
        confirmLabel="Simpan status unit"
        description={[
          'Status unit memengaruhi pilihan penempatan baru.',
          'Riwayat unit tetap tersimpan.',
        ]}
        pending={save.isPending}
        onConfirm={() => change(unit?.status === 'aktif' ? 'nonaktif' : 'aktif')}
      />
    </>
  )
}
