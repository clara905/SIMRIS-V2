import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const isSessionRequest = ['/api/me', '/login'].includes(error.config?.url)
    if (status === 401 && !isSessionRequest) window.dispatchEvent(new Event('simris:unauthorized'))
    if (status === 403)
      window.dispatchEvent(
        new CustomEvent('simris:forbidden', { detail: error.response?.data?.message }),
      )
    return Promise.reject(error)
  },
)

export function errorMessage(error: unknown): string {
  return axios.isAxiosError(error)
    ? error.response?.data?.message || 'Koneksi terputus. Periksa jaringan, lalu coba lagi.'
    : error instanceof Error
      ? error.message
      : 'Data tidak dapat diproses. Periksa isian, lalu coba lagi.'
}
export function fieldErrors(error: unknown): Record<string, string[]> {
  return axios.isAxiosError(error) && error.response?.status === 422
    ? error.response.data.errors || {}
    : {}
}
