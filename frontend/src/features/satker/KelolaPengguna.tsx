import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { api, errorMessage, fieldErrors } from '../../api/client'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import { useSession } from '../../api/auth'
import { getOptions, getPage, useSatkerUnit, type Option, type Row } from './api'
import { KeyRound, RefreshCw, ShieldCheck, UserPlus } from 'lucide-react'

type CreateForm = {
  pegawai_id: string
  username: string
  email_login: string
  password: string
  password_confirmation: string
}

type GrantForm = {
  role_id: string
  unit_id: string
  mulai: string
  selesai: string
}

type PasswordForm = {
  password: string
  password_confirmation: string
}

const emptyCreate: CreateForm = {
  pegawai_id: '',
  username: '',
  email_login: '',
  password: '',
  password_confirmation: '',
}

const todayStr = () => new Date().toISOString().slice(0, 10)

const emptyGrant: GrantForm = {
  role_id: '',
  unit_id: '',
  mulai: todayStr(),
  selesai: '',
}

const emptyPassword: PasswordForm = {
  password: '',
  password_confirmation: '',
}

const str = (value: unknown) => (value == null ? '' : String(value))
const first = (errors: Record<string, string[]>, key: string) => errors[key]?.[0]

export function KelolaPengguna() {
  const client = useQueryClient()
  const session = useSession()
  const satker = useSatkerUnit()
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [selected, setSelected] = useState<Row | null>(null)
  const [deactivating, setDeactivating] = useState<Row | null>(null)
  const [resetting, setResetting] = useState<Row | null>(null)
  const [revoking, setRevoking] = useState<Row | null>(null)
  const [createForm, setCreateForm] = useState<CreateForm>(emptyCreate)
  const [grantForm, setGrantForm] = useState<GrantForm>(emptyGrant)
  const [passwordForm, setPasswordForm] = useState<PasswordForm>(emptyPassword)
  const [currentPassword, setCurrentPassword] = useState('')
  const [revokePassword, setRevokePassword] = useState('')
  const [revokePasswordError, setRevokePasswordError] = useState('')
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const users = useQuery({
    queryKey: ['satker', 'users', page],
    queryFn: ({ signal }) =>
      getPage('/api/users', { page, per_page: 10, sort: '-id' }, signal),
  })

  const employees = useQuery({
    queryKey: ['satker', 'users', 'employees'],
    queryFn: ({ signal }) =>
      getOptions(
        '/api/pegawai',
        (row) => `${str(row.nama)}${row.nip ? ` (${row.nip})` : ''}`,
        signal,
      ),
  })

  const unitOptions = useQuery({
    queryKey: ['satker', 'users', 'units'],
    queryFn: ({ signal }) =>
      getOptions(
        '/api/unit-organisasi',
        (row) => `${str(row.kode)} — ${str(row.nama)}`,
        signal,
      ),
  })

  const roleOptions = useQuery({
    queryKey: ['satker', 'users', 'roles'],
    queryFn: async ({ signal }) => {
      const all = (await getPage('/api/roles', { per_page: 100 }, signal)).data
      return all
        .filter((row) => ['STAFF', 'KASUB', 'KABID', 'KASATKER'].includes(str(row.kode)))
        .map((row) => ({
          id: row.id,
          label: `${row.kode} — ${row.nama}`,
          code: str(row.kode),
        }))
    },
  })

  const scopes = useQuery({
    queryKey: ['satker', 'users', selected?.id, 'roles'],
    queryFn: ({ signal }) =>
      getPage(
        '/api/user-role-scope',
        { per_page: 100, sort: '-id', filter: { user_id: selected?.id } },
        signal,
      ),
    enabled: !!selected,
  })

  const refresh = () => {
    void client.invalidateQueries({ queryKey: ['satker', 'users'] })
    void client.invalidateQueries({ queryKey: ['satker', 'pegawai'] })
  }

  const create = useMutation({
    mutationFn: async () =>
      api.post('/api/users', {
        pegawai_id: Number(createForm.pegawai_id),
        username: createForm.username.trim(),
        email_login: createForm.email_login.trim(),
        password: createForm.password,
        password_confirmation: createForm.password_confirmation,
      }),
    onSuccess: () => {
      toast.success('Akun pengguna berhasil dibuat.')
      setCreating(false)
      setCreateForm(emptyCreate)
      setErrors({})
      refresh()
    },
    onError: (error) => {
      const validation = fieldErrors(error)
      setErrors(validation)
      const details = Object.values(validation).flat()
      toast.error(details.length ? details.join(' ') : errorMessage(error))
    },
  })

  const grant = useMutation({
    mutationFn: async () => {
      await api.post('/api/konfirmasi-password', { password: currentPassword })
      return api.post('/api/user-role-scope', {
        user_id: selected?.id,
        role_id: Number(grantForm.role_id),
        unit_id: Number(grantForm.unit_id),
        mulai: grantForm.mulai,
        ...(grantForm.selesai ? { selesai: grantForm.selesai } : {}),
      })
    },
    onSuccess: () => {
      toast.success('Role berhasil diberikan.')
      const defaultUnitId = unitOptions.data?.length === 1 ? String(unitOptions.data[0].id) : ''
      setGrantForm({ ...emptyGrant, unit_id: defaultUnitId })
      setCurrentPassword('')
      setErrors({})
      void client.invalidateQueries({ queryKey: ['satker', 'users', selected?.id, 'roles'] })
      refresh()
    },
    onError: (error) => {
      const validation = fieldErrors(error)
      if (validation.password) validation.current_password = validation.password
      setErrors(validation)
      toast.error(errorMessage(error))
    },
  })

  const userAction = useMutation({
    mutationFn: async ({ row, action }: { row: Row; action: 'aktifkan' | 'nonaktifkan' }) =>
      api.post('/api/users/' + row.id + '/' + action),
    onSuccess: (_, variables) => {
      toast.success(
        variables.action === 'aktifkan' ? 'Akun berhasil diaktifkan.' : 'Akun dinonaktifkan.',
      )
      setDeactivating(null)
      refresh()
    },
    onError: (error) => toast.error(errorMessage(error)),
  })

  const reset = useMutation({
    mutationFn: async () => {
      await api.post('/api/konfirmasi-password', { password: currentPassword })
      if (resetting) {
        await api.post('/api/users/' + resetting.id + '/reset-password', passwordForm)
      }
    },
    onSuccess: () => {
      toast.success('Kata sandi berhasil diatur ulang.')
      setResetting(null)
      setPasswordForm(emptyPassword)
      setCurrentPassword('')
      setErrors({})
      refresh()
    },
    onError: (error) => {
      const validation = fieldErrors(error)
      if (
        validation.password &&
        axios.isAxiosError(error) &&
        error.config?.url?.endsWith('/api/konfirmasi-password')
      ) {
        validation.current_password = validation.password
        delete validation.password
      }
      setErrors(validation)
      toast.error(errorMessage(error))
    },
  })

  const revoke = useMutation({
    mutationFn: async () => {
      await api.post('/api/konfirmasi-password', { password: revokePassword })
      if (revoking) await api.delete('/api/user-role-scope/' + revoking.id)
    },
    onSuccess: () => {
      toast.success('Role berhasil dicabut.')
      setRevoking(null)
      setRevokePassword('')
      setRevokePasswordError('')
      void client.invalidateQueries({ queryKey: ['satker', 'users', selected?.id, 'roles'] })
      refresh()
    },
    onError: () => undefined,
  })

  const openCreateDialog = () => {
    const defaultEmployeeId = employees.data?.length === 1 ? String(employees.data[0].id) : ''
    setCreateForm({
      ...emptyCreate,
      pegawai_id: defaultEmployeeId,
      username: satker.data?.kode ? `${String(satker.data.kode).toLowerCase().replace(/[^a-z0-9]/g, '')}.` : '',
    })
    setErrors({})
    setCreating(true)
  }

  const createSubmit = (event: FormEvent) => {
    event.preventDefault()
    const next: Record<string, string[]> = {}
    if (!createForm.pegawai_id) next.pegawai_id = ['Pilih pegawai.']
    if (!createForm.username.trim()) next.username = ['Username wajib diisi.']
    else if (!/^[A-Za-z0-9._-]+$/.test(createForm.username.trim())) {
      next.username = ['Gunakan huruf, angka, titik, garis bawah, atau tanda hubung.']
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(createForm.email_login.trim())) {
      next.email_login = ['Masukkan alamat email login yang valid.']
    }
    if (createForm.password.length < 12) next.password = ['Password minimal 12 karakter.']
    if (createForm.password !== createForm.password_confirmation) {
      next.password_confirmation = ['Konfirmasi password tidak sama.']
    }
    if (Object.keys(next).length) {
      setErrors(next)
      return
    }
    setErrors({})
    create.mutate()
  }

  const openGrantRole = (row: Row) => {
    setSelected(row)
    const defaultUnitId = unitOptions.data?.length === 1 ? String(unitOptions.data[0].id) : ''
    setGrantForm({
      ...emptyGrant,
      unit_id: defaultUnitId,
    })
    setErrors({})
  }

  const grantSubmit = (event: FormEvent) => {
    event.preventDefault()
    if (!grantForm.role_id || !grantForm.unit_id || !grantForm.mulai) {
      setErrors({
        role_id: !grantForm.role_id ? ['Pilih role.'] : [],
        unit_id: !grantForm.unit_id ? ['Pilih unit.'] : [],
        mulai: !grantForm.mulai ? ['Tanggal mulai wajib diisi.'] : [],
      })
      return
    }
    if (!currentPassword) {
      setErrors({ current_password: ['Masukkan kata sandi Anda untuk konfirmasi.'] })
      return
    }
    setErrors({})
    grant.mutate()
  }

  const privileged = (row: Row) =>
    row.dapat_diubah === false || str(row.alasan_tidak_dapat_diubah) !== ''

  const closeDrawer = () => {
    setSelected(null)
    setErrors({})
  }

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">Pengelolaan satker</span>
          <h1>Kelola pengguna &amp; role</h1>
          <p>
            Buat akun untuk pegawai satker dan kelola role operasional (STAFF, KASUB, KABID, KASATKER).
          </p>
        </div>
        <button className="primary" onClick={openCreateDialog}>
          <UserPlus size={16} /> Tambah pengguna
        </button>
      </div>

      <section className="ws-panel">
        <div className="ws-register-tools">
          <strong>Daftar pengguna dalam wilayah satker</strong>
          <button
            className="ws-refresh"
            aria-label="Perbarui pengguna"
            disabled={users.isFetching}
            onClick={() => void users.refetch()}
          >
            <RefreshCw size={16} />
          </button>
        </div>

        {users.isPending ? (
          <div className="ws-loading" role="status">
            Memuat pengguna...
          </div>
        ) : users.isError ? (
          <div className="ws-empty" role="alert">
            <strong>Daftar pengguna belum dapat dimuat</strong>
            <p>
              {errorMessage(users.error)} Jika akses ditolak (403), pastikan sesi akun Admin Satker
              Anda masih valid.
            </p>
            <button onClick={() => void users.refetch()}>Coba lagi</button>
          </div>
        ) : !users.data.data.length ? (
          <div className="ws-empty">
            <ShieldCheck size={28} />
            <strong>Belum ada pengguna di wilayah ini</strong>
            <p>Tambahkan akun untuk pegawai dengan penempatan aktif di unit satker.</p>
          </div>
        ) : (
          <>
            <div className="ws-table-wrap">
              <table className="ws-table">
                <thead>
                  <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Pegawai</th>
                    <th>Status</th>
                    <th>Aksi &amp; Kewenangan</th>
                  </tr>
                </thead>
                <tbody>
                  {users.data.data.map((row) => {
                    const own = row.id === session.data?.id
                    const locked = privileged(row) || own
                    const active = row.aktif === true || row.aktif === 1
                    return (
                      <tr key={row.id}>
                        <td>
                          <strong>{str(row.username)}</strong>
                        </td>
                        <td>{str(row.email_login)}</td>
                        <td>
                          {employees.data?.find(
                            (option) => option.id === Number(row.pegawai_id),
                          )?.label ?? '#' + row.pegawai_id}
                        </td>
                        <td>
                          <span className={'ws-badge ' + (active ? 'success' : 'neutral')}>
                            <span />
                            {active ? 'AKTIF' : 'NONAKTIF'}
                          </span>
                        </td>
                        <td>
                          {locked ? (
                            <span
                              style={{
                                color: 'var(--ws-text-muted, #64748b)',
                                fontSize: '0.85rem',
                              }}
                            >
                              {own
                                ? 'Akun Anda (hanya baca)'
                                : str(row.alasan_tidak_dapat_diubah) ||
                                  'Akun hak khusus (hanya baca)'}
                            </span>
                          ) : (
                            <div className="ws-register-tools">
                              <button type="button" onClick={() => openGrantRole(row)}>
                                Kelola role
                              </button>
                              {active && (
                                <button
                                  type="button"
                                  onClick={() => {
                                    setResetting(row)
                                    setPasswordForm(emptyPassword)
                                    setCurrentPassword('')
                                    setErrors({})
                                  }}
                                  aria-label={'Atur ulang password ' + row.username}
                                  title={'Atur ulang password ' + row.username}
                                >
                                  <KeyRound size={15} />
                                </button>
                              )}
                              <button
                                type="button"
                                disabled={userAction.isPending}
                                onClick={() =>
                                  active
                                    ? setDeactivating(row)
                                    : userAction.mutate({ row, action: 'aktifkan' })
                                }
                              >
                                {active ? 'Nonaktifkan' : 'Aktifkan'}
                              </button>
                            </div>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            <div className="ws-pagination">
              <span>
                {users.data.meta.from || 0}-{users.data.meta.to || 0} dari {users.data.meta.total}{' '}
                pengguna
              </span>
              <div>
                <button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
                  Sebelumnya
                </button>
                <span>
                  {users.data.meta.current_page} / {users.data.meta.last_page}
                </span>
                <button
                  disabled={page >= users.data.meta.last_page}
                  onClick={() => setPage((value) => value + 1)}
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
        onClose={() => setCreating(false)}
        title="Buat akun pengguna baru"
        description="Akun wajib terhubung ke pegawai dengan penempatan aktif di wilayah satker Anda. Password minimal 12 karakter."
      >
        <form className="ws-profile-form" onSubmit={createSubmit} noValidate>
          <div className="ws-profile-form-grid">
            <SelectField
              id="new-employee"
              label="Pegawai"
              value={createForm.pegawai_id}
              options={employees.data ?? []}
              error={first(errors, 'pegawai_id')}
              disabled={create.isPending || employees.isPending}
              onChange={(value) => setCreateForm((old) => ({ ...old, pegawai_id: value }))}
            />
            {employees.isError && (
              <p role="alert">Daftar pegawai tidak dapat dimuat: {errorMessage(employees.error)}</p>
            )}
            {!employees.isPending && !employees.isError && !employees.data?.length && (
              <p role="alert">
                Belum ada pegawai dengan penempatan aktif di wilayah satker. Buat pegawai dan
                penempatannya terlebih dahulu di menu Kelola Pegawai.
              </p>
            )}

            <TextField
              id="new-username"
              label="Username"
              value={createForm.username}
              error={first(errors, 'username')}
              disabled={create.isPending}
              onChange={(value) => setCreateForm((old) => ({ ...old, username: value }))}
            />
            <TextField
              id="new-email"
              label="Email login"
              type="email"
              value={createForm.email_login}
              error={first(errors, 'email_login')}
              disabled={create.isPending}
              onChange={(value) => setCreateForm((old) => ({ ...old, email_login: value }))}
            />
            <TextField
              id="new-password"
              label="Password awal (min. 12 karakter)"
              type="password"
              value={createForm.password}
              error={first(errors, 'password')}
              disabled={create.isPending}
              onChange={(value) => setCreateForm((old) => ({ ...old, password: value }))}
            />
            <TextField
              id="new-password-confirm"
              label="Konfirmasi password"
              type="password"
              value={createForm.password_confirmation}
              error={first(errors, 'password_confirmation')}
              disabled={create.isPending}
              onChange={(value) =>
                setCreateForm((old) => ({ ...old, password_confirmation: value }))
              }
            />
          </div>
          <div className="ws-profile-actions">
            <button className="primary" disabled={create.isPending}>
              {create.isPending ? 'Membuat akun...' : 'Buat akun'}
            </button>
          </div>
        </form>
      </Drawer>

      <Drawer
        open={!!selected}
        onClose={closeDrawer}
        title={'Kelola role: ' + str(selected?.username)}
        description="Pilihan role operasional: STAFF, KASUB, KABID, KASATKER dalam lingkup unit satker."
      >
        {selected && (
          <div className="ws-detail">
            {privileged(selected) || selected.id === session.data?.id ? (
              <p role="note">
                {str(selected.alasan_tidak_dapat_diubah) ||
                  (selected.id === session.data?.id
                    ? 'Akun Anda hanya dapat dilihat; Anda tidak dapat mengelola role akun sendiri.'
                    : 'Akun ini tidak dapat diubah oleh Admin Satker.')}
              </p>
            ) : (
              <form className="ws-profile-form" onSubmit={grantSubmit} noValidate>
                <h3>Berikan role operasional</h3>
                <div className="ws-profile-form-grid">
                  <SelectField
                    id="grant-role"
                    label="Role"
                    value={grantForm.role_id}
                    options={roleOptions.data ?? []}
                    error={first(errors, 'role_id')}
                    disabled={grant.isPending}
                    onChange={(value) => setGrantForm((old) => ({ ...old, role_id: value }))}
                  />
                  <SelectField
                    id="grant-unit"
                    label="Unit"
                    value={grantForm.unit_id}
                    options={unitOptions.data ?? []}
                    error={first(errors, 'unit_id')}
                    disabled={grant.isPending}
                    onChange={(value) => setGrantForm((old) => ({ ...old, unit_id: value }))}
                  />
                  <TextField
                    id="grant-start"
                    label="Tanggal mulai"
                    type="date"
                    value={grantForm.mulai}
                    error={first(errors, 'mulai')}
                    disabled={grant.isPending}
                    onChange={(value) => setGrantForm((old) => ({ ...old, mulai: value }))}
                  />
                  <TextField
                    id="grant-end"
                    label="Tanggal selesai (opsional)"
                    type="date"
                    value={grantForm.selesai}
                    error={first(errors, 'selesai')}
                    disabled={grant.isPending}
                    onChange={(value) => setGrantForm((old) => ({ ...old, selesai: value }))}
                  />
                  <TextField
                    id="grant-current-password"
                    label="Konfirmasi kata sandi Anda"
                    type="password"
                    value={currentPassword}
                    error={first(errors, 'current_password')}
                    disabled={grant.isPending}
                    onChange={setCurrentPassword}
                  />
                </div>

                {roleOptions.data?.find((role) => role.id === Number(grantForm.role_id))?.code ===
                  'KASATKER' && (
                  <p
                    role="alert"
                    style={{
                      background: '#fef3c7',
                      color: '#92400e',
                      padding: 10,
                      borderRadius: 6,
                      fontSize: '0.85rem',
                      fontWeight: 500,
                    }}
                  >
                    ⚠️ Perhatian: Role KASATKER berwenang mengesahkan dokumen dan penilaian risiko
                    pada tingkat satker.
                  </p>
                )}

                <div className="ws-profile-actions">
                  <button className="primary" disabled={grant.isPending}>
                    {grant.isPending ? 'Menyimpan...' : 'Berikan role'}
                  </button>
                </div>
              </form>
            )}

            <h3 style={{ marginTop: 24 }}>Role dan scope saat ini</h3>
            {scopes.isPending ? (
              <p role="status">Memuat role...</p>
            ) : scopes.isError ? (
              <p role="alert">{errorMessage(scopes.error)}</p>
            ) : !scopes.data.data.length ? (
              <p>Belum ada role terdaftar untuk akun ini.</p>
            ) : (
              <div className="ws-table-wrap">
                <table className="ws-table">
                  <thead>
                    <tr>
                      <th>Role</th>
                      <th>Unit</th>
                      <th>Mulai</th>
                      <th>Selesai</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    {scopes.data.data.map((scope) => {
                      const matchedRole = roleOptions.data?.find(
                        (r) => r.id === Number(scope.role_id),
                      )
                      const matchedUnit = unitOptions.data?.find(
                        (u) => u.id === Number(scope.unit_id),
                      )
                      return (
                        <tr key={scope.id}>
                          <td>{matchedRole?.label ?? '#' + scope.role_id}</td>
                          <td>{matchedUnit?.label ?? (scope.unit_id ? '#' + scope.unit_id : 'Global')}</td>
                          <td>{str(scope.mulai).slice(0, 10)}</td>
                          <td>{str(scope.selesai).slice(0, 10) || '-'}</td>
                          <td>
                            {selected.id !== session.data?.id &&
                              !privileged(selected) &&
                              scope.dapat_diubah !== false && (
                                <button
                                  className="ws-row-open"
                                  aria-label={'Cabut role ' + scope.role_id}
                                  onClick={() => setRevoking(scope)}
                                >
                                  Cabut
                                </button>
                              )}
                            {scope.dapat_diubah === false && (
                              <span title={str(scope.alasan_tidak_dapat_diubah)}>Hanya baca</span>
                            )}
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </Drawer>

      <Drawer
        open={!!resetting}
        onClose={() => {
          setResetting(null)
          setPasswordForm(emptyPassword)
          setCurrentPassword('')
          setErrors({})
        }}
        title={'Atur ulang password: ' + str(resetting?.username)}
        description="Masukkan kata sandi Anda untuk konfirmasi. Password baru minimal 12 karakter."
      >
        <form
          className="ws-profile-form"
          onSubmit={(event) => {
            event.preventDefault()
            const next: Record<string, string[]> = {}
            if (!currentPassword) next.current_password = ['Masukkan kata sandi Anda.']
            if (passwordForm.password.length < 12) {
              next.password = ['Password minimal 12 karakter.']
            }
            if (passwordForm.password !== passwordForm.password_confirmation) {
              next.password_confirmation = ['Konfirmasi password tidak sama.']
            }
            if (Object.keys(next).length) {
              setErrors(next)
              return
            }
            setErrors({})
            reset.mutate()
          }}
          noValidate
        >
          <div className="ws-profile-form-grid">
            <TextField
              id="reset-current-password"
              label="Kata sandi Anda"
              type="password"
              value={currentPassword}
              error={first(errors, 'current_password')}
              onChange={setCurrentPassword}
            />
            <TextField
              id="reset-password"
              label="Password baru (min. 12 karakter)"
              type="password"
              value={passwordForm.password}
              error={first(errors, 'password')}
              onChange={(value) => setPasswordForm((old) => ({ ...old, password: value }))}
            />
            <TextField
              id="reset-confirm"
              label="Konfirmasi password baru"
              type="password"
              value={passwordForm.password_confirmation}
              error={first(errors, 'password_confirmation')}
              onChange={(value) =>
                setPasswordForm((old) => ({ ...old, password_confirmation: value }))
              }
            />
          </div>
          <div className="ws-profile-actions">
            <button className="primary" disabled={reset.isPending}>
              {reset.isPending ? 'Mengatur ulang...' : 'Simpan kata sandi baru'}
            </button>
          </div>
        </form>
      </Drawer>

      <ConfirmDialog
        open={!!deactivating}
        onClose={() => setDeactivating(null)}
        title="Nonaktifkan akun pengguna?"
        description={[
          `Akun ${str(deactivating?.username)} tidak dapat digunakan untuk masuk sampai diaktifkan kembali.`,
          'Hak akses dan data audit tetap tersimpan.',
        ]}
        confirmLabel="Ya, nonaktifkan"
        pending={userAction.isPending}
        onConfirm={async () => {
          if (deactivating) await userAction.mutateAsync({ row: deactivating, action: 'nonaktifkan' })
        }}
      />

      <ConfirmDialog
        open={!!revoking}
        onClose={() => {
          setRevoking(null)
          setRevokePassword('')
          setRevokePasswordError('')
        }}
        title="Cabut role pengguna?"
        description={['Role akan dicabut dari pengguna ini. Tindakan ini tercatat pada log audit.']}
        confirmLabel="Cabut role"
        pending={revoke.isPending}
        passwordConfirmation={{
          value: revokePassword,
          onChange: (value) => {
            setRevokePassword(value)
            setRevokePasswordError('')
          },
          error: revokePasswordError,
        }}
        onConfirm={async () => {
          try {
            await revoke.mutateAsync()
          } catch (error) {
            if (fieldErrors(error).password?.[0]) {
              setRevokePasswordError(fieldErrors(error).password[0])
            } else {
              toast.error(errorMessage(error))
            }
          }
        }}
      />
    </>
  )
}

function TextField({
  id,
  label,
  value,
  error,
  type = 'text',
  disabled,
  onChange,
}: {
  id: string
  label: string
  value: string
  error?: string
  type?: string
  disabled?: boolean
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
        onChange={(event) => onChange(event.target.value)}
      />
      {error && <small role="alert">{error}</small>}
    </div>
  )
}

function SelectField({
  id,
  label,
  value,
  options,
  error,
  disabled,
  onChange,
}: {
  id: string
  label: string
  value: string
  options: Option[]
  error?: string
  disabled?: boolean
  onChange: (value: string) => void
}) {
  return (
    <div className="ws-profile-field">
      <label htmlFor={id}>{label}</label>
      <select
        id={id}
        value={value}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
      >
        <option value="">Pilih {label.toLowerCase()}</option>
        {options.map((option) => (
          <option key={option.id} value={option.id}>
            {option.label}
          </option>
        ))}
      </select>
      {error && <small role="alert">{error}</small>}
    </div>
  )
}
