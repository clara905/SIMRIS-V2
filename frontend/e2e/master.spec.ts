import { test, expect } from '@playwright/test'
import { demoSession } from './helpers'
test.beforeEach(async ({ page }) => {
  await demoSession(page)
})
test('CSV dipratinjau, baris salah ditolak, data valid diimpor', async ({ page }) => {
  await page.goto('/katalog')
  await page.getByRole('button', { name: 'Impor CSV' }).click()
  await page.getByLabel('Berkas CSV').setInputFiles({
    name: 'uji.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from('kode,nama,deskripsi\nANC-001,Duplikat,Data\nANC-001,Duplikat kedua,Data'),
  })
  await expect(page.getByRole('button', { name: 'Impor 2 data' })).toBeDisabled()
  await page.getByLabel('Berkas CSV').setInputFiles({
    name: 'uji.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from('kode,nama,deskripsi\nUJI-001,Ancaman pengujian,"Keterangan, dengan koma"'),
  })
  await page.getByRole('button', { name: 'Impor 1 data' }).click()
  await expect(page.getByRole('button', { name: 'Ancaman pengujian' })).toBeVisible()
  await page.getByRole('button', { name: 'Ekspor CSV' }).click()
  const download = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Unduh CSV' }).click()
  expect((await download).suggestedFilename()).toBe('simris-ancaman.csv')
})
test('periode memvalidasi urutan tanggal server dan kategori menampilkan pohon', async ({
  page,
}) => {
  await page.goto('/periode')
  await page.getByRole('button', { name: 'Tambah periode', exact: true }).click()
  await page.getByLabel('Kode', { exact: true }).fill('P-UJI')
  await page.getByLabel('Nama periode').fill('Periode uji')
  await page.getByLabel('Tanggal mulai').fill('2027-02-01')
  await page.getByLabel('Tanggal selesai').fill('2027-01-01')
  await page.getByRole('dialog').getByRole('button', { name: 'Tambah periode' }).click()
  await expect(page.getByRole('alert')).toContainText(
    'Tanggal selesai harus sama atau setelah tanggal mulai.',
  )
  await page.goto('/aset?tab=kategori')
  await expect(page.getByRole('navigation', { name: 'Pohon kategori' })).toBeVisible()
  await page.goto('/standar?tab=kontrol')
  await expect(page.getByRole('navigation', { name: 'Pohon kontrol' })).toBeVisible()
})
