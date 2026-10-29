import { http, HttpResponse, delay } from 'msw'
import type { Audit, Pegawai, Pengguna, Unit } from '../types'
import { audits, employees, units, users } from './seed'
import { isActive } from '../../lib/records'
export interface Database {
  pengguna: Pengguna[]
  unit: Unit[]
  pegawai: Pegawai[]
  audit: Audit[]
}
export function readDb(): Database {
  const saved = localStorage.getItem('simris-db-v1')
  return saved
    ? JSON.parse(saved)
    : structuredClone({ pengguna: users, unit: units, pegawai: employees, audit: audits })
}
export function writeDb(db: Database) {
  localStorage.setItem('simris-db-v1', JSON.stringify(db))
}
export function authorized() {
  const user = JSON.parse(sessionStorage.getItem('simris-session') || 'null')
  if (!user) return HttpResponse.json({ message: 'Sesi berakhir. Masuk kembali.' }, { status: 401 })
  if (user.role !== 'Super admin')
    return HttpResponse.json(
      { message: 'Role super admin aktif diperlukan untuk mengakses data ini.' },
      { status: 403 },
    )
}
export function validation(field: string, message: string) {
  return HttpResponse.json({ message, errors: { [field]: [message] } }, { status: 422 })
}
export function audit(
  db: Database,
  entitas: string,
  nama: string,
  before: object,
  after: object,
  aksi = 'Memperbarui',
) {
  db.audit.unshift({
    id: Date.now(),
    waktu: new Date().toISOString(),
    pelaku: JSON.parse(sessionStorage.getItem('simris-session') || '{}').nama || 'Pengguna',
    aksi,
    entitas:
      (
        { pengguna: 'Pengguna', unit: 'Unit organisasi', pegawai: 'Pegawai' } as Record<
          string,
          string
        >
      )[entitas] || entitas,
    nama,
    data_lama: { ...before },
    data_baru: { ...after },
  })
}
export function activeAt(end: string | null, start = '2000-01-01') {
  return isActive(end, start)
}
const moduleNames = ['pengguna', 'unit', 'pegawai'] as const
export const dataHandlers = [
  ...moduleNames.map((name) =>
    http.get(`/api/${name}`, async () => {
      const denied = authorized()
      if (denied) return denied
      await delay(120)
      const db = readDb()
      if (name === 'unit')
        db.unit.forEach((unit) => {
          unit.pegawai_aktif = db.pegawai.filter(
            (p) =>
              p.status === 'aktif' &&
              p.penempatan.some((v) => v.unit_id === unit.id && activeAt(v.berakhir, v.mulai)),
          ).length
        })
      return HttpResponse.json({ data: db[name] })
    }),
  ),
  ...moduleNames.flatMap((name) => [
    http.post(`/api/${name}`, ({ request }) => save(name, request)),
    http.put(`/api/${name}/:id`, ({ request, params }) => save(name, request, Number(params.id))),
  ]),
  http.post('/api/pengguna/:id/reset-password', ({ params }) => {
    const denied = authorized()
    if (denied) return denied
    const db = readDb()
    const user = db.pengguna.find((u) => u.id === Number(params.id))
    if (!user) return new HttpResponse(null, { status: 404 })
    const resets: string[] = JSON.parse(localStorage.getItem('simris-password-reset') || '[]')
    localStorage.setItem(
      'simris-password-reset',
      JSON.stringify([...new Set([...resets, user.email])]),
    )
    audit(db, 'Pengguna', user.nama, {}, { reset_password: true }, 'Mereset kata sandi')
    writeDb(db)
    return HttpResponse.json({
      message:
        'Reset kata sandi dicatat. Akun wajib mengganti kata sandi saat masuk berikutnya (simulasi; email tidak dikirim).',
    })
  }),
]
async function save(name: (typeof moduleNames)[number], request: Request, id?: number) {
  const denied = authorized()
  if (denied) return denied
  const body = (await request.json()) as Record<string, unknown>
  const db = readDb()
  const old = db[name].find((v) => v.id === id)
  if (id && !old) return new HttpResponse(null, { status: 404 })
  if (typeof body.nama !== 'string' || !body.nama.trim())
    return validation('nama', 'Nama wajib diisi. Masukkan nama data.')
  if (name === 'pengguna') {
    if (!/^\S+@\S+\.\S+$/.test(String(body.email)))
      return validation('email', 'Masukkan alamat email yang valid.')
    if (db.pengguna.some((u) => u.email === body.email && u.id !== id))
      return validation('email', 'Email sudah digunakan. Masukkan email lain.')
    if (id && body.status === 'nonaktif') {
      const current = JSON.parse(sessionStorage.getItem('simris-session') || '{}')
      if (id === current.id) return validation('status', 'Akun sendiri tidak dapat dinonaktifkan.')
      const admins = db.pengguna.filter(
        (u) =>
          u.status === 'aktif' &&
          u.roles.some((r) => r.role === 'Super admin' && activeAt(r.berakhir, r.mulai)),
      )
      if (admins.length === 1 && admins[0].id === id)
        return validation('status', 'Super admin terakhir tidak dapat dinonaktifkan.')
    }
    const value = {
      roles: [],
      terakhir_masuk: '',
      status: 'aktif',
      ...old,
      ...body,
      id: id || Date.now(),
    } as Pengguna
    if (
      value.roles.some(
        (role) =>
          !db.unit.some((unit) => unit.id === role.unit_id && unit.status === 'aktif') ||
          !role.mulai ||
          (role.berakhir && role.berakhir < role.mulai),
      )
    )
      return validation('roles', 'Role scope memerlukan unit aktif dan rentang tanggal yang valid.')
    const admins = db.pengguna.filter(
      (u) =>
        u.status === 'aktif' &&
        u.roles.some((role) => role.role === 'Super admin' && activeAt(role.berakhir, role.mulai)),
    )
    if (
      admins.length === 1 &&
      admins[0].id === id &&
      !value.roles.some(
        (role) => role.role === 'Super admin' && activeAt(role.berakhir, role.mulai),
      )
    )
      return validation(
        'roles',
        'Role super admin terakhir harus tetap aktif. Tetapkan super admin lain terlebih dahulu.',
      )
    db.pengguna = id ? db.pengguna.map((u) => (u.id === id ? value : u)) : [...db.pengguna, value]
  }
  if (name === 'unit') {
    if (!body.kode) return validation('kode', 'Kode unit wajib diisi.')
    if (db.unit.some((u) => u.kode === body.kode && u.id !== id))
      return validation('kode', 'Kode unit sudah digunakan.')
    if (
      body.status === 'nonaktif' &&
      (db.unit.some((u) => u.induk_id === id) ||
        db.pegawai.some(
          (p) =>
            p.status === 'aktif' &&
            p.penempatan.some((v) => v.unit_id === id && activeAt(v.berakhir, v.mulai)),
        ))
    )
      return validation(
        'status',
        'Unit masih memiliki anak atau pegawai aktif. Pindahkan terlebih dahulu.',
      )
    let parent = Number(body.induk_id)
    const seen = new Set<number>()
    while (parent) {
      if (parent === id || seen.has(parent))
        return validation('induk_id', 'Induk tidak boleh membentuk siklus. Pilih unit lain.')
      seen.add(parent)
      parent = db.unit.find((u) => u.id === parent)?.induk_id || 0
    }
    const value = {
      status: 'aktif',
      pimpinan: '',
      pegawai_aktif: 0,
      induk_id: null,
      ...old,
      ...body,
      id: id || Date.now(),
    } as Unit
    db.unit = id ? db.unit.map((u) => (u.id === id ? value : u)) : [...db.unit, value]
  }
  if (name === 'pegawai') {
    if (body.nip && !/^\d{1,18}$/.test(String(body.nip)))
      return validation('nip', 'NIP hanya boleh berisi 1 sampai 18 digit angka.')
    if (db.pegawai.some((p) => p.nip === body.nip && p.id !== id))
      return validation('nip', 'NIP sudah terdaftar. Gunakan NIP yang berbeda.')
    const value = {
      status: 'aktif',
      penempatan: [],
      ...old,
      ...body,
      id: id || Date.now(),
    } as Pegawai
    db.pegawai = id ? db.pegawai.map((p) => (p.id === id ? value : p)) : [...db.pegawai, value]
  }
  const saved = db[name].find((v) => v.id === (id || db[name].at(-1)?.id))!
  audit(db, name, saved.nama, old || {}, saved, id ? 'Memperbarui' : 'Menambahkan')
  writeDb(db)
  return HttpResponse.json({ data: saved }, { status: id ? 200 : 201 })
}
