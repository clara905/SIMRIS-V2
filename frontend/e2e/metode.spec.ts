import { test, expect } from '@playwright/test'
import { demoSession } from './helpers'
test.beforeEach(async ({ page }) => {
  await demoSession(page)
})
test('membuat, melengkapi, membekukan, dan menduplikasi metode', async ({ page }) => {
  await page.goto('/metode-risiko')
  await page.getByRole('button', { name: 'Buat versi metode', exact: true }).click()
  await page.getByLabel('Nama versi', { exact: true }).fill('Metode uji 2028')
  await page.getByLabel('Deskripsi', { exact: true }).fill('Pedoman uji pembekuan.')
  await page.getByRole('dialog').getByRole('button', { name: 'Buat versi metode' }).click()
  await page.getByRole('button', { name: 'Bekukan versi', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Lengkapi sebelum membekukan' })).toBeVisible()
  await page.getByRole('button', { name: 'Lengkapi kemungkinan 1, dampak 1' }).click()
  await page.getByRole('button', { name: 'Pilih semua sel' }).click()
  await page.getByLabel('Tingkat risiko untuk sel terpilih').selectOption('3')
  await page.getByRole('button', { name: 'Terapkan tingkat' }).click()
  await expect(page.getByText('Tingkat risiko diterapkan', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Bekukan versi', exact: true }).click()
  await expect(
    page.getByRole('alertdialog').getByRole('button', { name: 'Bekukan versi' }),
  ).toBeDisabled()
  await page.getByLabel('Ketik', { exact: false }).fill('Metode uji 2028')
  await page.getByRole('alertdialog').getByRole('button', { name: 'Bekukan versi' }).click()
  await expect(
    page.getByText(
      'Versi ini sudah dibekukan dan tidak bisa diubah. Duplikasi versi untuk membuat perubahan.',
    ),
  ).toBeVisible()
  await expect(page.getByLabel(/^Dibekukan /)).toBeVisible()
  await expect(page.getByRole('button', { name: 'Terapkan tingkat' })).toHaveCount(0)
  for (const tab of [
    'Skala kemungkinan',
    'Skala dampak',
    'Area dan kriteria dampak',
    'Tingkat risiko',
  ]) {
    await page.getByRole('tab', { name: tab, exact: true }).click()
    await expect(page.getByRole('tabpanel').getByRole('button')).toHaveCount(0)
  }
  await page.getByRole('button', { name: 'Duplikasi versi' }).click()
  await page.getByLabel('Nama versi baru').fill('Metode salinan 2029')
  await page.getByRole('dialog').getByRole('button', { name: 'Duplikasi versi' }).click()
  await expect(page.getByRole('heading', { name: 'Metode salinan 2029' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Bekukan versi' })).toBeVisible()
})
