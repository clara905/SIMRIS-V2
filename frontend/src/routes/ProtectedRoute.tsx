import { useEffect } from 'react'
import { Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { sessionKey, useSession } from '../api/auth'
import { errorMessage } from '../api/client'
import axios from 'axios'
export function ProtectedRoute() {
  const session = useSession()
  const location = useLocation()
  const navigate = useNavigate()
  const client = useQueryClient()
  useEffect(() => {
    const expired = () => {
      client.removeQueries({ queryKey: sessionKey })
      navigate('/masuk', { replace: true })
    }
    const forbidden = (event: Event) =>
      navigate('/tanpa-akses', { state: { reason: (event as CustomEvent<string>).detail } })
    window.addEventListener('simris:unauthorized', expired)
    window.addEventListener('simris:forbidden', forbidden)
    return () => {
      window.removeEventListener('simris:unauthorized', expired)
      window.removeEventListener('simris:forbidden', forbidden)
    }
  }, [client, navigate])
  if (session.isPending)
    return (
      <main className="session-loading" aria-label="Memuat sesi">
        <div className="skeleton" />
        <div className="skeleton" />
        <p role="status">Memeriksa sesi…</p>
      </main>
    )
  if (session.isError) {
    if (axios.isAxiosError(session.error) && session.error.response?.status === 401)
      return <Navigate to="/masuk" state={{ from: location.pathname }} replace />
    return (
      <main className="session-loading">
        <h1>Sesi belum dapat diperiksa</h1>
        <p role="alert">{errorMessage(session.error)}</p>
        <button onClick={() => session.refetch()}>Coba lagi</button>
      </main>
    )
  }
  if (session.data.wajib_ganti_password && location.pathname !== '/ganti-password')
    return <Navigate to="/ganti-password" replace />
  if (
    import.meta.env.VITE_API_MOCK === 'true'
      ? session.data.role !== 'Super admin'
      : !session.data.roleCodes?.length
  )
    return (
      <Navigate
        to="/tanpa-akses"
        state={{
          reason:
            'Akun ini tidak memiliki role aktif yang sesuai. Hubungi pengelola akun untuk meninjau role scope Anda.',
        }}
        replace
      />
    )
  return <Outlet />
}
