import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { api } from '../../api/client'
import { BuatAsesmenPage } from './BuatAsesmenPage'

vi.mock('../../api/auth', () => ({
  useSession: () => ({ data: { id: 1, roleCodes: ['STAFF'] }, isPending: false }),
}))
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

const likelihoods = Array.from({ length: 5 }, (_, index) => ({ id: index + 1, nilai: index + 1, label: `Kemungkinan ${index + 1}`, kriteria: '' }))
const impacts = Array.from({ length: 5 }, (_, index) => ({ id: index + 11, nilai: index + 1, label: `Dampak ${index + 1}`, kriteria: '' }))
const matrix = likelihoods.flatMap((likelihood) => impacts.map((impact) => ({
  id: likelihood.nilai * 10 + impact.nilai,
  kemungkinan_id: likelihood.id,
  dampak_id: impact.id,
  skor: likelihood.nilai + impact.nilai,
  tingkat_risiko_id: 50,
})))

function response(data: unknown[]) {
  return { data: { data, meta: { current_page: 1, last_page: 1, total: data.length } } } as never
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(<QueryClientProvider client={client}><MemoryRouter><BuatAsesmenPage /></MemoryRouter></QueryClientProvider>)
}

afterEach(cleanup)
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
    if (url === '/api/periode-penilaian') return response([
      { id: 2, nama: 'Periode aktif', tanggal_mulai: '2020-01-01', tanggal_selesai: '2099-12-31' },
      { id: 1, nama: 'Periode lampau', tanggal_mulai: '2010-01-01', tanggal_selesai: '2010-12-31' },
    ])
    if (url === '/api/versi-metode-risiko') return response([
      { id: 3, nama: 'Metode lama', versi: 1, dibekukan: true, profil_penilaian: false },
      { id: 8, nama: 'Metode terbaru', versi: 2, dibekukan: true, profil_penilaian: true },
    ])
    if (url === '/api/skala-kemungkinan') return response(likelihoods)
    if (url === '/api/skala-dampak') return response(impacts)
    if (url === '/api/tingkat-risiko') return response([{ id: 50, kode: 'LOW', nama: 'Rendah', warna: '#008800' }])
    if (url === '/api/matriks-risiko') return response(matrix)
    throw new Error(`Unexpected request: ${url}`)
  })
})

it('uses server-selected unit and latest frozen method without sending either from the client', async () => {
  const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: { id: 99 } } } as never)
  renderPage()

  expect(await screen.findByText('Matriks 5x5')).toBeInTheDocument()
  expect(screen.queryByRole('option', { name: /Periode lampau/ })).not.toBeInTheDocument()
  expect(screen.getByText('Ditentukan otomatis dari penempatan utama STAFF Anda.')).toBeInTheDocument()
  expect(await screen.findByText(/Pratinjau matriks 5×5/)).toBeInTheDocument()

  const user = userEvent.setup()
  await user.selectOptions(screen.getByLabelText('Periode asesmen'), '2')
  await user.type(screen.getByLabelText('Tujuan asesmen'), 'Menjaga layanan')
  await user.type(screen.getByLabelText('Ruang lingkup'), 'Sistem layanan internal')
  await user.click(screen.getByRole('button', { name: 'Buat draft asesmen' }))

  await waitFor(() => expect(post).toHaveBeenCalledWith('/api/asesmen', {
    periode_id: 2,
    tujuan: 'Menjaga layanan',
    ruang_lingkup: 'Sistem layanan internal',
  }))
})
