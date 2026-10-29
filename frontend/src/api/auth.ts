import { useQuery } from '@tanstack/react-query'
import { api } from './client'
import type { components } from './schema'
export type SessionUser = components['schemas']['SessionUser'] & {
  roleCodes?: string[]
  unitIds?: number[]
  username?: string
  pegawai?: {
    id: number
    nip?: string | null
    nip_tersedia?: boolean
    nama: string
    email_kontak: string
    jenis_kepegawaian?: string | null
  } | null
}
export interface BackendSession {
  user: { id: number; username: string; email_login: string; wajib_ganti_password: boolean }
  pegawai: { id: number; nip?: string | null; nip_tersedia?: boolean; nama: string; email_kontak: string; jenis_kepegawaian?: string | null } | null
  roles: { kode: string; nama: string; unit_id: number | null }[]
}
export function normalizeSession(data: SessionUser | BackendSession): SessionUser {
  if (!('user' in data)) return data
  return {
    id: data.user.id,
    username: data.user.username,
    nama: data.pegawai?.nama || data.user.username,
    email: data.user.email_login,
    pegawai: data.pegawai,
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
export async function login(values: { login: string; password: string; totp?: string }) {
  await api.get('/sanctum/csrf-cookie')
  await api.post('/login', {
    login: values.login,
    password: values.password,
    ...(values.totp ? { totp: values.totp } : {}),
  })
}

export async function register(values: {
  username: string
  email: string
  password: string
  password_confirmation: string
}) {
  await api.get('/sanctum/csrf-cookie')
  return (await api.post<{ data: SessionUser | BackendSession }>('/daftar', values)).data
}
export async function updateProfile(values: {
  nama: string
  email: string
  nip?: string | null
  jenis_kepegawaian?: string | null
}) {
  return (await api.put<{ data: SessionUser | BackendSession }>('/api/profil', values)).data
}
export async function revealNip(employeeId: number, password: string): Promise<string> {
  const response = await api.post<{ data: { nip: string | null } }>(
    `/api/pegawai/${employeeId}/lihat-nip`,
    { password },
  )
  return response.data.data.nip ?? ''
}
export async function logout() {
  await api.post('/logout')
}
