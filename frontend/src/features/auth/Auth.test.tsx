import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, expect, it } from 'vitest'
import userEvent from '@testing-library/user-event'
import { LoginPage } from './AuthPages'
describe('Formulaire de connexion', () => {
  it('mencegah masuk tanpa email dan kata sandi', async () => {
    render(
      <QueryClientProvider client={new QueryClient()}>
        <MemoryRouter>
          <LoginPage />
        </MemoryRouter>
      </QueryClientProvider>,
    )
    await userEvent.click(screen.getByRole('button', { name: 'Masuk ke ruang kerja' }))
    expect(await screen.findByText('Masukkan username atau email.')).toBeInTheDocument()
    expect(screen.getByText('Masukkan kata sandi.')).toBeInTheDocument()
  })
})
