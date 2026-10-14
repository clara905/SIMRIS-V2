import { useQuery } from '@tanstack/react-query'
import { api } from './client'
import type { components } from './schema'
export type SessionUser = components['schemas']['SessionUser']
export const sessionKey = ['session']
export function useSession() {
  return useQuery({
    queryKey: sessionKey,
    queryFn: async () => (await api.get<{ data: SessionUser }>('/api/me')).data.data,
    retry: false,
    staleTime: 60_000,
  })
}
export async function login(values: { email: string; password: string }) {
  await api.get('/sanctum/csrf-cookie')
  await api.post('/login', values)
}
export async function logout() {
  await api.post('/logout')
}
