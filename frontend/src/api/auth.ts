import { useQuery } from '@tanstack/react-query'
import { api } from './client'
import type { components } from './schema'
export type SessionUser = components['schemas']['SessionUser'] & {
  roleCodes?: string[]
  unitIds?: number[]
}
export interface BackendSession {
  user: { id: number; username: string; email_login: string; wajib_ganti_password: boolean }
  pegawai: { nama: string } | null
  roles: { kode: string; nama: string; unit_id: number | null }[]
}
export function normalizeSession(data: SessionUser | BackendSession): SessionUser {
  if (!('user' in data)) return data
  return {
    id: data.user.id,
    nama: data.pegawai?.nama || data.user.username,
    email: data.user.email_login,
    wajib_ganti_password: data.user.wajib_ganti_password,
    role: data.roles.map((role) => role.nama).join(', ') || 'Tanpa role aktif',
    roleCodes: data.roles.map((role) => role.kode),
    unitIds: [
      ...new Set(data.roles.flatMap((role) => (role.unit_id === null ? [] : [role.unit_id]))),
    ],
  }
}
export const sessionKey = ['session']
export function useSession() {
  return useQuery({
    queryKey: sessionKey,
    queryFn: async () =>
      normalizeSession(
        (await api.get<{ data: SessionUser | BackendSession }>('/api/me')).data.data,
      ),
    retry: false,
    staleTime: 60_000,
  })
}
export async function login(values: { email: string; password: string; totp?: string }) {
  await api.get('/sanctum/csrf-cookie')
  await api.post(
    '/login',
    import.meta.env.VITE_API_MOCK === 'true'
      ? values
      : {
          login: values.email,
          password: values.password,
          ...(values.totp ? { totp: values.totp } : {}),
        },
  )
}
export async function logout() {
  await api.post('/logout')
}
