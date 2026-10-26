import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, expect, it, vi } from 'vitest'
import { MatriksGrid } from './MatriksGrid'
const scales = [
  { nilai: 1, nama: 'Rendah', kriteria: 'Jarang terjadi' },
  { nilai: 2, nama: 'Tinggi', kriteria: 'Sering terjadi' },
]
afterEach(cleanup)
it('menandai sel kosong dan mendukung pemilihan beberapa sel', async () => {
  const select = vi.fn()
  render(
    <MatriksGrid
      kemungkinan={scales}
      dampak={scales}
      tingkat={[]}
      cells={[]}
      selected={['1-1', '2-2']}
      onSelect={select}
    />,
  )
  expect(screen.getAllByText('Belum ditetapkan')).toHaveLength(4)
  expect(screen.getAllByRole('button', { pressed: true })).toHaveLength(2)
  await userEvent.click(
    screen.getByRole('button', { name: 'Kemungkinan 1, dampak 2: Belum ditetapkan' }),
  )
  expect(select).toHaveBeenCalledWith('1-2')
})
it('matriks beku tidak mempunyai tombol pengubah', () => {
  render(<MatriksGrid kemungkinan={scales} dampak={scales} tingkat={[]} cells={[]} readOnly />)
  expect(screen.queryAllByRole('button')).toHaveLength(0)
  expect(screen.getAllByText('Belum ditetapkan')).toHaveLength(4)
})
