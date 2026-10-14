import { cleanup, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { DataTable } from './DataTable'
import { TreeView } from './TreeView'
import { ConfirmDialog } from './ConfirmDialog'
import { RiskLevelChip } from './RiskLevelChip'
import { contrastText, luminance } from '../lib/contrast'
afterEach(cleanup)
describe('DataTable', () => {
  it('trie et pagine le registre et ouvre une ligne', async () => {
    const select = vi.fn()
    render(
      <DataTable
        caption="Registre"
        data={Array.from({ length: 12 }, (_, i) => ({
          id: i,
          nama: `Unit ${String(i).padStart(2, '0')}`,
        }))}
        columns={[{ key: 'nama', label: 'Nama' }]}
        onSelect={select}
        userId={99}
      />,
    )
    expect(screen.getAllByRole('row')).toHaveLength(11)
    await userEvent.click(screen.getByRole('button', { name: 'Halaman berikutnya' }))
    expect(screen.getAllByRole('row')).toHaveLength(3)
    await userEvent.click(screen.getByRole('button', { name: 'Unit 10' }))
    expect(select).toHaveBeenCalledWith({ id: 10, nama: 'Unit 10' })
    await userEvent.selectOptions(screen.getByLabelText('Kepadatan tabel'), 'rapat')
    expect(localStorage.getItem('simris-density-99')).toBe('rapat')
  })
})
it('pohon dapat diciutkan dan dipilih lewat keyboard', async () => {
  const select = vi.fn()
  render(
    <TreeView
      nodes={[{ id: 1, label: 'Induk', children: [{ id: 2, label: 'Anak' }] }]}
      onSelect={select}
    />,
  )
  await userEvent.click(screen.getByRole('button', { name: 'Ciutkan Induk' }))
  expect(screen.queryByRole('button', { name: 'Anak' })).not.toBeInTheDocument()
  await userEvent.keyboard('{Enter}')
  await userEvent.click(screen.getByRole('button', { name: 'Anak' }))
  expect(select).toHaveBeenCalledWith(2)
})
it('konfirmasi hanya aktif setelah nama versi cocok', async () => {
  const confirm = vi.fn()
  render(
    <ConfirmDialog
      open
      onClose={vi.fn()}
      title="Bekukan versi"
      description={['Tidak bisa diubah.']}
      confirmation="Metode 2026"
      confirmLabel="Bekukan versi"
      onConfirm={confirm}
    />,
  )
  const dialog = screen.getByRole('alertdialog')
  const button = within(dialog).getByRole('button', { name: 'Bekukan versi' })
  expect(button).toBeDisabled()
  await userEvent.type(screen.getByLabelText(/Ketik/), 'Metode 2026')
  expect(button).toBeEnabled()
  await userEvent.click(button)
  expect(confirm).toHaveBeenCalledOnce()
})
it('nama risiko selalu terlihat dan pasangan warna memenuhi AA', () => {
  const colors = ['#2e8b7a', '#8fb339', '#e3b23c', '#e07b39', '#c23b3b', '#ffffff', '#111111']
  colors.forEach((color) => {
    const l1 = luminance(color)
    const l2 = luminance(contrastText(color))
    expect((Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)).toBeGreaterThanOrEqual(4.5)
  })
  render(<RiskLevelChip name="Sangat tinggi" color="#c23b3b" />)
  expect(screen.getByText('Sangat tinggi')).toBeVisible()
})
