
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

    const isSessionRequest = [
      '/api/me',
      '/api/v1/me',
      '/v1/me',
      '/me',
      '/login',
      '/api/v1/login',
      '/api/v1/daftar',
    ].includes(url)

    if (status === 401 && !isSessionRequest) {
      window.dispatchEvent(new Event('simris:unauthorized'))
    }

    if (status === 403) {
      window.dispatchEvent(
        new CustomEvent('simris:forbidden', {
          detail: 'Permintaan tidak dapat diproses. Silakan coba lagi.',
        }),
      )
    }

    return Promise.reject(error)
  },
)

export function errorMessage(error: unknown): string {
  if (axios.isAxiosError(error)) {
    return 'Permintaan tidak dapat diproses. Silakan coba lagi.'
  }

  return 'Permintaan tidak dapat diproses. Silakan coba lagi.'
}

export function fieldErrors(error: unknown): Record<string, string[]> {
  return axios.isAxiosError(error) && error.response?.status === 422
    ? error.response.data.errors || {}
    : {}
}
