import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, expect, it, vi } from 'vitest'
import { api } from '../../api/client'
import { RoleRequestPage } from './RoleRequestPage'

afterEach(() => {
  cleanup()
  vi.restoreAllMocks()
})

it('does not send a unit id for STAFF because the server uses the active placement', async () => {
  vi.spyOn(api, 'get').mockImplementation(async (url: string) => {
    if (url === '/api/v1/pengajuan-role') {
      return { data: { data: { has_role: false, pengajuan: null } } } as never
    }
    if (url === '/api/v1/unit-pilihan-role?role=STAFF') {
      return { data: { data: [] } } as never
    }
    throw new Error(`Unexpected request: ${url}`)
  })
  const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: {} } } as never)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <RoleRequestPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )

  await userEvent.type(screen.getByLabelText('Informasi pendukung'), 'Saya bertugas pada unit ini.')
  await userEvent.click(screen.getByRole('button', { name: 'Kirim pengajuan' }))

  await waitFor(() => expect(post).toHaveBeenCalledWith('/api/v1/pengajuan-role', {
    role_kode: 'STAFF',
    informasi_pendukung: 'Saya bertugas pada unit ini.',
  }))
})
