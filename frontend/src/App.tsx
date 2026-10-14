import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { lazy, Suspense } from 'react'
import { BrowserRouter, Link, Route, Routes } from 'react-router-dom'
import { Toaster } from 'sonner'
import { AppShell } from './components/AppShell'
import { ProtectedRoute } from './routes/ProtectedRoute'
import { ForbiddenPage, LoginPage, PasswordPage } from './features/auth/AuthPages'
import { Skeleton } from './components/States'
const Gallery = lazy(() => import('./components/Gallery').then((m) => ({ default: m.Gallery })))
const PenggunaPage = lazy(() => import('./features/pengguna/PenggunaPage'))
const UnitPage = lazy(() => import('./features/unit/UnitPage'))
const PegawaiPage = lazy(() => import('./features/pegawai/PegawaiPage'))
const MetodePage = lazy(() => import('./features/metode-risiko/MetodePage'))
const EditorPage = lazy(() => import('./features/metode-risiko/EditorPage'))
const KatalogPage = lazy(() => import('./features/katalog/KatalogPage'))
const StandarPage = lazy(() => import('./features/standar/StandarPage'))
const AsetPage = lazy(() => import('./features/aset/AsetPage'))
const PeriodePage = lazy(() => import('./features/periode/PeriodePage'))
const BerandaPage = lazy(() => import('./features/beranda/BerandaPage'))
const MonitoringPage = lazy(() => import('./features/monitoring/MonitoringPage'))
const AsesmenPage = lazy(() => import('./features/monitoring/AsesmenPage'))
const AuditPage = lazy(() => import('./features/audit/AuditPage'))
const queryClient = new QueryClient({
  defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } },
})
import Items from "./components/Items";


export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Suspense
          fallback={
            <main className="session-loading">
              <Skeleton />
            </main>
          }
        >
          <Routes>
            <Route path="/masuk" element={<LoginPage />} />
            <Route path="/tanpa-akses" element={<ForbiddenPage />} />
            <Route element={<ProtectedRoute />}>
              <Route path="/ganti-password" element={<PasswordPage />} />
              <Route element={<AppShell />}>
                <Route index element={<BerandaPage />} />
                <Route path="/pengguna" element={<PenggunaPage />} />
                <Route path="/unit" element={<UnitPage />} />
                <Route path="/pegawai" element={<PegawaiPage />} />
                <Route path="/metode-risiko" element={<MetodePage />} />
                <Route path="/metode-risiko/:id" element={<EditorPage />} />
                <Route path="/katalog" element={<KatalogPage />} />
                <Route path="/standar" element={<StandarPage />} />
                <Route path="/aset" element={<AsetPage />} />
                <Route path="/periode" element={<PeriodePage />} />
                <Route path="/monitoring" element={<MonitoringPage />} />
                <Route path="/monitoring/asesmen/:id" element={<AsesmenPage />} />
                <Route path="/audit" element={<AuditPage />} />
                <Route path="/dev/komponen" element={<Gallery />} />
                <Route
                  path="*"
                  element={
                    <>
                      <h1>Halaman tidak ditemukan</h1>
                      <p>Periksa alamat halaman atau kembali ke ruang kerja.</p>
                      <Link to="/">Kembali ke beranda</Link>
                    </>
                  }
                />
              </Route>
            </Route>
          </Routes>
        </Suspense>
      </BrowserRouter>
      <Toaster position="bottom-right" />
    </QueryClientProvider>
  )
}
