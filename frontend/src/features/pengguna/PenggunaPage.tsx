import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Plus, Info, KeyRound } from 'lucide-react'
import { toast } from 'sonner'
import { usePengguna, usePenggunaAction, useSavePengguna } from '../../api/pengguna'
import { useUnit } from '../../api/unit'
import { useSession } from '../../api/auth'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { FilterBar } from '../../components/FilterBar'
import { DataTable } from '../../components/DataTable'
import { Drawer } from '../../components/Drawer'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { RecordForm } from '../../components/RecordForm'
import { StatusBadge } from '../../components/StatusBadge'
import { ErrorState, Skeleton } from '../../components/States'
import { tanggal } from '../../lib/format'
import { isActive } from '../../lib/records'
import s from '../Records.module.css'

export default function PenggunaPage() {
  const query = usePengguna()
  const units = useUnit()
  const session = useSession()
  const save = useSavePengguna()
  const action = usePenggunaAction()
  const [params] = useSearchParams()
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState(params.get('filter') === 'tanpa-role' ? 'tanpa-role' : '')
  const [selected, setSelected] = useState<number | null>(null)
  const [form, setForm] = useState<'new' | 'edit' | 'role' | null>(null)
  const [editingRole, setEditingRole] = useState<number | null>(null)
  const [confirm, setConfirm] = useState<'disable' | 'reset' | null>(null)
  const user = query.data?.find((u) => u.id === selected)
  const roleToEdit = user?.roles.find((role) => role.id === editingRole)
  const admins =
    query.data?.filter(
      (u) =>
        u.status === 'aktif' &&
        u.roles.some((r) => r.role === 'Super admin' && isActive(r.berakhir, r.mulai)),
    ) || []
  const blocked =
    user?.id === session.data?.id
      ? 'Akun sendiri tidak dapat dinonaktifkan.'
      : admins.length === 1 && admins[0].id === user?.id
        ? 'Super admin terakhir tidak dapat dinonaktifkan.'
        : ''
  const close = () => {
    setSelected(null)
    setForm(null)
  }
  const data = (query.data || []).filter(
    (u) =>
      `${u.nama} ${u.email}`.toLowerCase().includes(search.toLowerCase()) &&
      (!status ||
        (status === 'tanpa-role'
          ? !u.roles.some((r) => isActive(r.berakhir, r.mulai))
          : u.status === status)),
  )
  return (
    <>
      <PageHeader
        title="Pengguna"
        description="Kelola akun, hak akses, dan cakupan kewenangan pengguna."
        actions={
          <button
            className="primary"
            onClick={() => {
              setSelected(null)
              setForm('new')
            }}
          >
            <Plus size={17} />
            Tambah pengguna
          </button>
        }
      />
      <FilterBar value={search} onChange={setSearch} placeholder="Cari nama atau email…">
        <select
          aria-label="Filter status"
          value={status}
          onChange={(e) => setStatus(e.target.value)}
        >
          <option value="">Semua status</option>
          <option value="aktif">Aktif</option>
          <option value="nonaktif">Nonaktif</option>
          <option value="tanpa-role">Tanpa role aktif</option>
        </select>
        <span className={s.muted}>{query.data?.length || 0} akun terdaftar</span>
      </FilterBar>
      {query.isPending ? (
        <Skeleton />
      ) : query.isError ? (
        <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
      ) : (
        <DataTable
          userId={session.data?.id}
          caption="Daftar pengguna"
          data={data}
          onSelect={(u) => {
            setSelected(u.id)
            setForm(null)
          }}
          columns={[
            { key: 'nama', label: 'Nama pengguna' },
            { key: 'email', label: 'Email' },
            {
              key: 'roles',
              label: 'Role aktif',
              render: (u) =>
                u.roles
                  .filter((r) => isActive(r.berakhir, r.mulai))
                  .map((r) => r.role)
                  .join(', ') || 'Belum ada role',
            },
            { key: 'status', label: 'Status', render: (u) => <StatusBadge status={u.status} /> },
            {
              key: 'terakhir_masuk',
              label: 'Terakhir masuk',
              render: (u) => (u.terakhir_masuk ? tanggal(u.terakhir_masuk) : 'Belum pernah'),
            },
          ]}
        />
      )}
      <Drawer
        open={!!user || form === 'new'}
        onClose={close}
        title={
          form === 'new'
            ? 'Tambah pengguna'
            : form === 'edit'
              ? 'Ubah pengguna'
              : form === 'role'
                ? editingRole
                  ? 'Ubah role scope'
                  : 'Tambah role scope'
                : user?.nama || ''
        }
        description={
          form
            ? 'Lengkapi data dan simpan perubahan.'
            : 'Akun, cakupan kewenangan, dan riwayat role.'
        }
      >
        {(form === 'new' || form === 'edit') && (
          <RecordForm
            key={form}
            fields={[
              { name: 'nama', label: 'Nama lengkap' },
              { name: 'email', label: 'Email', type: 'email' },
            ]}
            values={form === 'edit' && user ? { nama: user.nama, email: user.email } : {}}
            onCancel={close}
            submitLabel={form === 'new' ? 'Tambah pengguna' : 'Simpan perubahan'}
            onSave={async (values) => {
              const result = await save.mutateAsync({ ...(form === 'edit' ? user : {}), ...values })
              setSelected(result.id)
              setForm(null)
              toast.success(form === 'new' ? 'Pengguna ditambahkan' : 'Perubahan disimpan')
            }}
          />
        )}
        {form === 'role' && user && (
          <RecordForm
            fields={[
              {
                name: 'role',
                label: 'Role',
                type: 'select',
                options: [
                  'Super admin',
                  'Kasatker',
                  'Kabid',
                  'Pemilik risiko',
                  'Operator',
                  'Auditor',
                ].map((value) => ({ value, label: value })),
              },
              {
                name: 'unit_id',
                label: 'Cakupan unit',
                type: 'select',
                options: (units.data || [])
                  .filter((u) => u.status === 'aktif')
                  .map((u) => ({ value: String(u.id), label: u.nama })),
              },
              { name: 'mulai', label: 'Berlaku mulai', type: 'date' },
              { name: 'berakhir', label: 'Berlaku sampai', type: 'date', required: false },
            ]}
            values={
              roleToEdit
                ? {
                    role: roleToEdit.role,
                    unit_id: String(roleToEdit.unit_id),
                    mulai: roleToEdit.mulai,
                    berakhir: roleToEdit.berakhir || '',
                  }
                : {
                    mulai: new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(
                      new Date(),
                    ),
                  }
            }
            onCancel={() => setForm(null)}
            submitLabel={editingRole ? 'Simpan role scope' : 'Tambah role scope'}
            onSave={async (v) => {
              if (v.berakhir && v.berakhir < v.mulai)
                throw new Error('Tanggal berakhir mendahului tanggal mulai.')
              await save.mutateAsync({
                ...user,
                roles: [
                  ...user.roles.filter((role) => role.id !== editingRole),
                  {
                    id: editingRole || Date.now(),
                    role: v.role,
                    unit_id: Number(v.unit_id),
                    unit_nama: units.data!.find((u) => u.id === Number(v.unit_id))!.nama,
                    mulai: v.mulai,
                    berakhir: v.berakhir || null,
                  },
                ],
              })
              setForm(null)
              toast.success('Role scope disimpan')
            }}
          />
        )}
        {!form && user && (
          <>
            <StatusBadge status={user.status} />
            <dl className={s.meta}>
              <dt>Email</dt>
              <dd>{user.email}</dd>
              <dt>Terakhir masuk</dt>
              <dd>{user.terakhir_masuk ? tanggal(user.terakhir_masuk) : 'Belum pernah masuk'}</dd>
            </dl>
            <div className={s.actions}>
              <button onClick={() => setForm('edit')}>Ubah pengguna</button>
              <button onClick={() => setConfirm('reset')}>
                <KeyRound size={16} />
                Reset kata sandi
              </button>
            </div>
            <section className={s.section}>
              <div className={s.sectionTitle}>
                <h2>Role scope aktif</h2>
                <button
                  className="link-button"
                  onClick={() => {
                    setEditingRole(null)
                    setForm('role')
                  }}
                >
                  <Plus size={16} />
                  Tambah role scope
                </button>
              </div>
              {!user.roles.some((r) => isActive(r.berakhir, r.mulai)) && (
                <p className={s.muted}>
                  Belum ada role aktif. Tambahkan role scope agar pengguna dapat mengakses unit yang
                  sesuai.
                </p>
              )}
              <ul className={s.list}>
                {user.roles
                  .filter((r) => isActive(r.berakhir, r.mulai))
                  .map((role) => (
                    <li key={role.id}>
                      <strong>{role.role}</strong>
                      <button
                        className="link-button"
                        onClick={() => {
                          setEditingRole(role.id)
                          setForm('role')
                        }}
                      >
                        Ubah role scope
                      </button>
                      {role.unit_nama}
                      <small>
                        {tanggal(role.mulai)} —{' '}
                        {role.berakhir ? tanggal(role.berakhir) : 'Tanpa batas akhir'}
                      </small>
                    </li>
                  ))}
              </ul>
            </section>
            <section className={s.section}>
              <h2>Riwayat role</h2>
              <ul className={s.list}>
                {user.roles
                  .filter((r) => !isActive(r.berakhir, r.mulai))
                  .map((role) => (
                    <li key={role.id}>
                      <strong>
                        {role.role} · {role.unit_nama}
                      </strong>
                      <small>
                        {tanggal(role.mulai)} —{' '}
                        {role.berakhir ? tanggal(role.berakhir) : 'Belum mulai'}
                      </small>
                    </li>
                  ))}
              </ul>
              {!user.roles.some((r) => !isActive(r.berakhir, r.mulai)) && (
                <p className={s.muted}>Belum ada role yang berakhir.</p>
              )}
            </section>
            <section className={s.section}>
              <button
                disabled={!!blocked}
                aria-describedby="disable-reason"
                onClick={() => setConfirm('disable')}
              >
                {user.status === 'aktif' ? 'Nonaktifkan pengguna' : 'Aktifkan pengguna'}
              </button>
              <p id="disable-reason" className={s.muted}>
                {blocked || 'Akun dan jejak audit tetap tersimpan.'}
              </p>
            </section>
          </>
        )}
      </Drawer>
      <ConfirmDialog
        open={!!confirm}
        onClose={() => setConfirm(null)}
        title={
          confirm === 'reset'
            ? 'Reset kata sandi'
            : user?.status === 'aktif'
              ? 'Nonaktifkan pengguna'
              : 'Aktifkan pengguna'
        }
        confirmLabel={confirm === 'reset' ? 'Reset kata sandi' : 'Simpan status pengguna'}
        description={
          confirm === 'reset'
            ? [
                'Instruksi penggantian kata sandi akan dibuat untuk akun ini.',
                'Pengguna perlu mengganti kata sandi pada saat masuk berikutnya.',
              ]
            : [
                'Perubahan status memengaruhi akses akun ini.',
                'Data dan jejak audit tidak dihapus.',
              ]
        }
        pending={save.isPending || action.isPending}
        onConfirm={async () => {
          if (!user) return
          try {
            if (confirm === 'reset') {
              const result = await action.mutateAsync({ id: user.id, action: 'reset-password' })
              toast.success(result.message)
            } else {
              await save.mutateAsync({
                ...user,
                status: user.status === 'aktif' ? 'nonaktif' : 'aktif',
              })
              toast.success('Status pengguna diperbarui')
            }
            setConfirm(null)
          } catch (error) {
            toast.error(errorMessage(error))
          }
        }}
      />
      <div className={s.notice}>
        <Info size={16} />
        <p>
          Hak akses mengikuti role scope aktif. Penonaktifan akun mempertahankan data dan jejak
          audit pengguna.
        </p>
      </div>
    </>
  )
}
