import { http, HttpResponse, delay } from 'msw'
import type { SessionUser } from '../auth'
import { activeAt, dataHandlers, readDb } from './data'
import { metodeHandlers } from './metode'
import { masterHandlers } from './master'
import { monitoringHandlers } from './monitoring'

const demo: SessionUser = {
  id: 1,
  nama: 'Aditya Pratama',
  email: 'admin@simris.go.id',
  wajib_ganti_password: false,
  role: 'Super admin',
}
const session = () =>
  JSON.parse(sessionStorage.getItem('simris-session') || 'null') as SessionUser | null
async function digest(value: string) {
  const bytes = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(value))
  return Array.from(new Uint8Array(bytes), (b) => b.toString(16).padStart(2, '0')).join('')
}
export const handlers = [
  ...dataHandlers,
  ...metodeHandlers,
  ...masterHandlers,
  ...monitoringHandlers,
  http.get('/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
  http.post('/login', async ({ request }) => {
    await delay(150)
    const values = (await request.json()) as { email: string; password: string }
    const account = readDb().pengguna.find((u) => u.email === values.email && u.status === 'aktif')
    const passwords: Record<string, string> = JSON.parse(
      localStorage.getItem('simris-demo-passwords') || '{}',
    )
    const resetEmails: string[] = JSON.parse(localStorage.getItem('simris-password-reset') || '[]')
    if (
      (!account && !['baru@simris.go.id', 'terbatas@simris.go.id'].includes(values.email)) ||
      (passwords[values.email]
        ? (await digest(values.password)) !== passwords[values.email]
        : values.password !== 'Simris123!')
    ) {
      return HttpResponse.json(
        {
          message: 'Email atau kata sandi tidak cocok. Periksa kembali keduanya.',
          errors: { email: ['Email atau kata sandi tidak cocok.'] },
        },
        { status: 422 },
      )
    }
    sessionStorage.setItem(
      'simris-session',
      JSON.stringify({
        ...demo,
        id: account?.id || demo.id,
        nama: account?.nama || demo.nama,
        email: values.email,
        wajib_ganti_password:
          resetEmails.includes(values.email) ||
          (values.email.startsWith('baru') && !passwords[values.email]),
        role: account
          ? account.roles.some((r) => r.role === 'Super admin' && activeAt(r.berakhir, r.mulai))
            ? 'Super admin'
            : account.roles.find((r) => activeAt(r.berakhir, r.mulai))?.role || 'Tanpa role'
          : values.email.startsWith('terbatas')
            ? 'Auditor'
            : 'Super admin',
      }),
    )
    return new HttpResponse(null, { status: 204 })
  }),
  http.get('/api/me', () =>
    session()
      ? HttpResponse.json({ data: session() })
      : HttpResponse.json({ message: 'Sesi berakhir. Masuk kembali.' }, { status: 401 }),
  ),
  http.post('/logout', () => {
    sessionStorage.removeItem('simris-session')
    return new HttpResponse(null, { status: 204 })
  }),
  http.post('/api/password', async ({ request }) => {
    if (!session()) return new HttpResponse(null, { status: 401 })
    const body = (await request.json()) as { password: string; password_confirmation: string }
    if (body.password.length < 12 || body.password !== body.password_confirmation)
      return HttpResponse.json(
        {
          message: 'Periksa kata sandi baru.',
          errors: { password: ['Gunakan sedikitnya 12 karakter dan konfirmasi yang sama.'] },
        },
        { status: 422 },
      )
    sessionStorage.setItem(
      'simris-session',
      JSON.stringify({ ...session(), wajib_ganti_password: false }),
    )
    const passwords: Record<string, string> = JSON.parse(
      localStorage.getItem('simris-demo-passwords') || '{}',
    )
    passwords[session()!.email] = await digest(body.password)
    localStorage.setItem('simris-demo-passwords', JSON.stringify(passwords))
    const resets: string[] = JSON.parse(localStorage.getItem('simris-password-reset') || '[]')
    localStorage.setItem(
      'simris-password-reset',
      JSON.stringify(resets.filter((email) => email !== session()!.email)),
    )
    return new HttpResponse(null, { status: 204 })
  }),
]
