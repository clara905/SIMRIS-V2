
import axios from 'axios'

export const api = axios.create({
  // Gunakan same-origin proxy Vite saat dev agar cookie Sanctum tetap di host frontend.
  baseURL: import.meta.env.VITE_API_URL || '',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const url = error.config?.url ?? ''

    const isSessionRequest = ['/api/me', '/api/v1/me', '/v1/me', '/me', '/login', '/api/v1/login'].includes(url)

    if (status === 401 && !isSessionRequest) {
      window.dispatchEvent(new Event('simris:unauthorized'))
    }

    if (status === 403) {
      window.dispatchEvent(
        new CustomEvent('simris:forbidden', {
          detail: error.response?.data?.message,
        }),
      )
    }

    return Promise.reject(error)
  },
)

export function errorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data

    if (data?.message) return data.message

    if (data?.errors) {
      const firstError = Object.values(
        data.errors as Record<string, string[]>,
      )[0]?.[0]

      if (firstError) return firstError
    }

    if (!error.response) {
      return 'Tidak dapat menghubungi server. Pastikan backend aktif di http://127.0.0.1:8000.'
    }

    if (error.response.status >= 500) {
      return 'Server mengalami error. Periksa log Laravel untuk detailnya.'
    }

    return `Permintaan gagal (HTTP ${error.response.status}). Periksa input dan konfigurasi login.`
  }

  return error instanceof Error
    ? error.message
    : 'Data tidak dapat diproses. Periksa isian, lalu coba lagi.'
}

export function fieldErrors(error: unknown): Record<string, string[]> {
  return axios.isAxiosError(error) && error.response?.status === 422
    ? error.response.data.errors || {}
    : {}
}
