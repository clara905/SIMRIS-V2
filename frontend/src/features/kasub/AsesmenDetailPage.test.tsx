import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { api } from '../../api/client'
import { toast } from 'sonner'
import { ProposalEditor, RatingEditor } from './AsesmenDetailPage'

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

const likelihoods = [1, 2, 3, 4, 5].map((nilai) => ({
  id: 100 + nilai,
  nilai,
  label: `Kemungkinan ${nilai}`,
  kriteria: `Kriteria kemungkinan ${nilai}`,
}))
const impacts = [1, 2, 3, 4, 5].map((nilai) => ({
  id: 200 + nilai,
  nilai,
  label: `Dampak ${nilai}`,
  kriteria: `Kriteria dampak ${nilai}`,
}))
const levels = [
  { id: 1, kode: 'RENDAH', nama: 'Rendah', warna: '#008800', batas_min_skor: 1, batas_max_skor: 4, perlu_rtp: false, perlu_persetujuan_pemilik: false, kriteria_penerimaan: 'Diterima' },
  { id: 2, kode: 'SEDANG', nama: 'Sedang', warna: '#aaaa00', batas_min_skor: 5, batas_max_skor: 9, perlu_rtp: false, perlu_persetujuan_pemilik: true, kriteria_penerimaan: 'Persetujuan' },
  { id: 3, kode: 'TINGGI', nama: 'Tinggi', warna: '#cc6600', batas_min_skor: 10, batas_max_skor: 16, perlu_rtp: true, perlu_persetujuan_pemilik: false, kriteria_penerimaan: 'RTP wajib' },
  { id: 4, kode: 'SANGAT_TINGGI', nama: 'Sangat Tinggi', warna: '#cc0000', batas_min_skor: 17, batas_max_skor: 25, perlu_rtp: true, perlu_persetujuan_pemilik: false, kriteria_penerimaan: 'RTP wajib' },
]
const matrixRows = [
  { id: 302, kemungkinan_id: likelihoods[1].id, dampak_id: impacts[2].id, skor: 7, tingkat_risiko_id: levels[1].id },
  { id: 310, kemungkinan_id: likelihoods[1].id, dampak_id: impacts[4].id, skor: 10, tingkat_risiko_id: levels[2].id },
]

function renderWithClient(element: ReactNode) {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return render(<QueryClientProvider client={client}>{element}</QueryClientProvider>)
}

function axiosError(status: number, message: string, errors?: Record<string, string[]>) {
  return Object.assign(new Error(message), {
    isAxiosError: true,
    response: { status, data: { message, errors } },
  })
}

afterEach(cleanup)
beforeEach(() => vi.clearAllMocks())

it('membedakan pratinjau hitungan klien dari nilai server yang tersimpan', async () => {
  const rating = {
    id: 1,
    tahap: 'SAAT_INI',
    matriks_id: 310,
    skor: 10,
    alasan_kemungkinan: 'Alasan',
    alasan_dampak: 'Alasan',
    kesimpulan: 'Tersimpan',
    matriks: { id: 310, skor: 10, kemungkinan_id: likelihoods[1].id, dampak_id: impacts[4].id, tingkat_risiko_id: levels[2].id },
    tingkat: levels[2],
    kemungkinan: likelihoods[1],
    dampak: impacts[4],
  } as NonNullable<Parameters<typeof RatingEditor>[0]['rating']>
  renderWithClient(
    <RatingEditor
      riskId={1}
      stage="SAAT_INI"
      rating={rating}
      matrices={[]}
      matrixRows={matrixRows}
      likelihoods={likelihoods}
      impacts={impacts}
      levels={levels}
      profile
      editable
      refresh={vi.fn()}
    />,
  )

  expect(screen.getByText('Nilai server: Tinggi · skor 10')).toBeInTheDocument()
  await userEvent.selectOptions(screen.getByRole('combobox', { name: /^Kemungkinan/ }), '2')
  await userEvent.selectOptions(screen.getByRole('combobox', { name: /^Dampak/ }), '3')
  expect(screen.getByText('Pratinjau (belum disimpan): skor 7 · Sedang')).toBeInTheDocument()
  await userEvent.selectOptions(screen.getByRole('combobox', { name: /^Dampak/ }), '5')
  expect(document.querySelector('.assessment-rating-preview')).toHaveTextContent('skor 10 · Tinggi · Wajib RTP')
  expect(screen.getByText('Nilai server: Tinggi · skor 10')).toBeInTheDocument()
})

it('menampilkan galat validasi 422 pada field rating', async () => {
  vi.spyOn(api, 'post').mockRejectedValueOnce(axiosError(422, 'Validasi gagal', {
    kemungkinan_nilai: ['Kemungkinan wajib diisi.'],
  }))
  renderWithClient(
    <RatingEditor
      riskId={1}
      stage="SAAT_INI"
      matrices={[]}
      matrixRows={matrixRows}
      likelihoods={likelihoods}
      impacts={impacts}
      levels={levels}
      profile
      editable
      refresh={vi.fn()}
    />,
  )
  const user = userEvent.setup()
  await user.selectOptions(screen.getByLabelText('Kemungkinan'), '2')
  await user.selectOptions(screen.getByLabelText('Dampak'), '3')
  await user.type(screen.getByLabelText('Alasan kemungkinan'), 'Alasan')
  await user.type(screen.getByLabelText('Alasan dampak'), 'Alasan')
  await user.type(screen.getByLabelText('Kesimpulan'), 'Kesimpulan')
  await user.click(screen.getByRole('button', { name: 'Simpan penilaian' }))
  expect(await screen.findByRole('alert')).toHaveTextContent('Kemungkinan wajib diisi.')
})

it('menampilkan pesan backend untuk penolakan RTP 409', async () => {
  vi.spyOn(api, 'post').mockRejectedValueOnce(axiosError(409, 'Risiko tinggi wajib RTP.'))
  renderWithClient(<ProposalEditor riskId={1} refresh={vi.fn()} />)
  const user = userEvent.setup()
  expect(screen.getByRole('option', { name: 'Modifikasi / Mitigasi' })).toBeInTheDocument()
  expect(screen.queryByRole('option', { name: /Hindari|Bagikan/ })).not.toBeInTheDocument()
  await user.selectOptions(screen.getByLabelText('Opsi perlakuan'), 'TERIMA')
  await user.type(screen.getByLabelText('Alasan / ringkasan rencana penanganan (RTP)'), 'Usulan')
  await user.click(screen.getByRole('button', { name: 'Simpan usulan' }))
  await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Risiko tinggi wajib RTP.'))
})
