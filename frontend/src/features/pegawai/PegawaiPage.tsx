import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { usePegawai, useSavePegawai } from '../../api/pegawai'
import { useUnit } from '../../api/unit'
import { useSession } from '../../api/auth'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { DataTable } from '../../components/DataTable'
import { FilterBar } from '../../components/FilterBar'
import { Drawer } from '../../components/Drawer'
import { RecordForm } from '../../components/RecordForm'
import { StatusBadge } from '../../components/StatusBadge'
import { EmptyState, ErrorState, Skeleton } from '../../components/States'
import { isActive, unitPath } from '../../lib/records'
import { tanggal } from '../../lib/format'
import s from '../Records.module.css'
export default function PegawaiPage() {
  const query = usePegawai()
  const units = useUnit()
  const save = useSavePegawai()
  const session = useSession()
  const [params] = useSearchParams()
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState(params.get('unit') || params.get('filter') || '')
  const [selected, setSelected] = useState<number | null>(Number(params.get('pegawai')) || null)
  const [form, setForm] = useState<'new' | 'edit' | 'placement' | null>(null)
  const person = query.data?.find((p) => p.id === selected)
  const placements = person?.penempatan.filter((v) => isActive(v.berakhir, v.mulai)) || []
  const close = () => {
    setSelected(null)
    setForm(null)
  }
  const data = (query.data || []).filter(
    (p) =>
      `${p.nama} ${p.nip}`.toLowerCase().includes(search.toLowerCase()) &&
      (!filter ||
        (filter === 'tanpa-penempatan'
          ? !p.penempatan.some((v) => isActive(v.berakhir, v.mulai))
          : p.penempatan.some(
              (v) => String(v.unit_id) === filter && isActive(v.berakhir, v.mulai),
            ))),
  )
  return (
    <>
      <PageHeader
        title="Pegawai"
        description="Pelihara identitas pegawai dan riwayat penempatan dalam organisasi."
        actions={
          <button
            className="primary"
            onClick={() => {
              setSelected(null)
              setForm('new')
            }}
          >
            <Plus size={17} />
            Tambah pegawai
          </button>
        }
      />
      <FilterBar value={search} onChange={setSearch} placeholder="Cari nama atau NIP…">
        <select
          aria-label="Filter penempatan"
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
        >
          <option value="">Semua penempatan</option>
          <option value="tanpa-penempatan">Tanpa penempatan aktif</option>
          {units.data?.map((u) => (
            <option key={u.id} value={u.id}>
              {u.nama}
            </option>
          ))}
        </select>
      </FilterBar>
      {query.isPending ? (
        <Skeleton />
      ) : query.isError ? (
        <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
      ) : (
        <DataTable
          userId={session.data?.id}
          caption="Daftar pegawai"
          data={data}
          onSelect={(p) => {
            setSelected(p.id)
            setForm(null)
          }}
          columns={[
            { key: 'nama', label: 'Nama pegawai' },
            { key: 'nip', label: 'NIP' },
            {
              key: 'penempatan',
              label: 'Penempatan aktif',
              render: (p) =>
                p.penempatan
                  .filter((v) => isActive(v.berakhir, v.mulai))
                  .map((v) => v.unit_nama)
                  .join(', ') || 'Belum ada penempatan',
            },
            { key: 'status', label: 'Status', render: (p) => <StatusBadge status={p.status} /> },
          ]}
        />
      )}
      <Drawer
        open={!!person || form === 'new'}
        onClose={close}
        title={
          form === 'new'
            ? 'Tambah pegawai'
            : form === 'edit'
              ? 'Ubah pegawai'
              : form === 'placement'
                ? 'Tambah penempatan'
                : person?.nama || ''
        }
      >
        {(form === 'new' || form === 'edit') && (
          <RecordForm
            key={form}
            fields={[
              { name: 'nama', label: 'Nama lengkap' },
              {
                name: 'nip',
                label: 'NIP',
                pattern: /^\d{18}$/,
                hint: 'Gunakan 18 angka tanpa spasi.',
              },
              { name: 'email', label: 'Email', type: 'email' },
            ]}
            values={
              form === 'edit' && person
                ? { nama: person.nama, nip: person.nip, email: person.email }
                : {}
            }
            onCancel={close}
            submitLabel={form === 'new' ? 'Tambah pegawai' : 'Simpan perubahan'}
            onSave={async (values) => {
              const result = await save.mutateAsync({
                ...(form === 'edit' ? person : {}),
                ...values,
              })
              setSelected(result.id)
              setForm(null)
              toast.success('Pegawai disimpan')
            }}
          />
        )}
        {form === 'placement' && person && (
          <>
            <div className={s.notice}>
              Penempatan aktif sebelumnya akan diakhiri sehari sebelum tanggal mulai penempatan
              baru. Riwayat tetap tersimpan.
            </div>
            <RecordForm
              fields={[
                {
                  name: 'unit_id',
                  label: 'Unit penempatan',
                  type: 'select',
                  options: (units.data || [])
                    .filter((u) => u.status === 'aktif')
                    .map((u) => ({
                      value: String(u.id),
                      label: unitPath(units.data || [], u.id).join(' / '),
                    })),
                },
                { name: 'jabatan', label: 'Jabatan' },
                { name: 'mulai', label: 'Tanggal mulai', type: 'date' },
              ]}
              values={{
                mulai: new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(
                  new Date(),
                ),
              }}
              onCancel={() => setForm(null)}
              submitLabel="Simpan penempatan"
              onSave={async (v) => {
                if (placements.some((p) => v.mulai <= p.mulai))
                  throw new Error('Tanggal mulai harus setelah penempatan sebelumnya.')
                const previousDate = new Date(v.mulai)
                previousDate.setUTCDate(previousDate.getUTCDate() - 1)
                await save.mutateAsync({
                  ...person,
                  penempatan: [
                    ...person.penempatan.map((p) =>
                      isActive(p.berakhir, p.mulai)
                        ? { ...p, berakhir: previousDate.toISOString().slice(0, 10) }
                        : p,
                    ),
                    {
                      id: Date.now(),
                      unit_id: Number(v.unit_id),
                      unit_nama: units.data!.find((u) => u.id === Number(v.unit_id))!.nama,
                      jabatan: v.jabatan,
                      mulai: v.mulai,
                      berakhir: null,
                    },
                  ],
                })
                setForm(null)
                toast.success('Penempatan disimpan')
              }}
            />
          </>
        )}
        {!form && person && (
          <>
            <StatusBadge status={person.status} />
            <dl className={s.meta}>
              <dt>NIP</dt>
              <dd>{person.nip}</dd>
              <dt>Email</dt>
              <dd>{person.email}</dd>
            </dl>
            <button onClick={() => setForm('edit')}>Ubah pegawai</button>
            <section className={s.section}>
              <h2>Jalur organisasi aktif</h2>
              {placements.map((p) => (
                <div key={p.id}>
                  <p className={s.path}>{unitPath(units.data || [], p.unit_id).join(' / ')}</p>
                  <p>{p.jabatan}</p>
                </div>
              ))}
              {!placements.length && (
                <EmptyState
                  title="Belum ada penempatan"
                  description="Tambahkan penempatan agar pegawai ini muncul di struktur unit."
                  action={<button onClick={() => setForm('placement')}>Tambah penempatan</button>}
                />
              )}
              {!!placements.length && (
                <button onClick={() => setForm('placement')}>Tambah penempatan</button>
              )}
            </section>
            <section className={s.section}>
              <h2>Riwayat penempatan</h2>
              <ol className={s.timeline}>
                {[...person.penempatan]
                  .sort((a, b) => b.mulai.localeCompare(a.mulai))
                  .map((p) => (
                    <li key={p.id}>
                      <strong>{p.unit_nama}</strong>
                      <p>{p.jabatan}</p>
                      <small>
                        {tanggal(p.mulai)} — {p.berakhir ? tanggal(p.berakhir) : 'Sekarang'}
                      </small>
                    </li>
                  ))}
              </ol>
            </section>
          </>
        )}
      </Drawer>
    </>
  )
}
