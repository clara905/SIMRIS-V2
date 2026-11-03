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
export interface LaravelSession {
  id: number
  username: string
  email: string
  nama: string
  aktif: boolean
  peran: { kode: string; nama: string; unit: { id: number } | null }[]
}
export function normalizeSession(data: SessionUser | BackendSession | LaravelSession): SessionUser {
  if ('peran' in data) {
    return {
      id: data.id,
      username: data.username,
      nama: data.nama,
      email: data.email,
      wajib_ganti_password: false,
      role: data.peran.map((role) => role.nama).join(', ') || 'Tanpa role aktif',
      roleCodes: data.peran.map((role) => role.kode),
      unitIds: [...new Set(data.peran.flatMap((role) => (role.unit ? [role.unit.id] : [])))],
    }
  }
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
    queryFn: async () => {
      const response = (await api.get<
        { data: SessionUser | BackendSession | LaravelSession } | LaravelSession
      >('/api/me')).data
      return normalizeSession('data' in response ? response.data : response)
    },
    retry: false,
    staleTime: 60_000,
  })
}
export async function loginWithFirebase(values: { email: string; password: string; captcha_token: string }) {
  await api.get('/sanctum/csrf-cookie')
  return (await api.post('/api/v1/login', values)).data
}

export async function registerWithFirebase(values: {
  nip: string
  nama_lengkap: string
  email: string
  id_token: string
  captcha_token: string
}) {
  await api.get('/sanctum/csrf-cookie')
  const { data } = await api.post<{
    data: SessionUser | BackendSession | LaravelSession
    message: string
  }>('/api/v1/daftar', values)
  return data
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
