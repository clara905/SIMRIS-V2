import { test, expect } from '@playwright/test'
import { demoSession } from './helpers'
test.beforeEach(async ({ page }) => {
  await demoSession(page)
})
test('monitoring hanya-lihat pada semua jenis dan detail asesmen', async ({ page }) => {
  await page.goto('/monitoring')
  for (const tab of ['Asesmen', 'SOA', 'SOP', 'Dokumen', 'Lampiran']) {
    await page.getByRole('tab', { name: tab, exact: true }).click()
    await expect(
      page.getByText('Hanya lihat. Super admin tidak mengubah data transaksi.', { exact: true }),
    ).toBeVisible()
    await expect(page.getByRole('button', { name: /^(Ubah|Setujui|Hapus|Tambah)/ })).toHaveCount(0)
  }
  await page.getByRole('tab', { name: 'Asesmen', exact: true }).click()
  await page.getByRole('button', { name: 'Layanan pusat data', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Lembar asesmen' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Riwayat versi dokumen' })).toBeVisible()
})
test('beranda menautkan temuan dan audit memperlihatkan diff', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Perlu ditindaklanjuti' })).toBeVisible()
  await page.getByRole('button', { name: 'Pemantauan', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Pemantauan', exact: true })).toHaveAttribute(
    'aria-pressed',
    'true',
  )
  await expect(page.getByRole('link', { name: /Pegawai tanpa penempatan aktif/ })).toHaveCount(0)
  await expect(page.getByRole('link', { name: /Lampiran perlu diperiksa/ })).toBeVisible()
  await page.getByRole('button', { name: 'Semua prioritas', exact: true }).click()
  await page.getByRole('link', { name: /Pegawai tanpa penempatan aktif/ }).click()
  await expect(page.getByLabel('Filter penempatan')).toHaveValue('tanpa-penempatan')
  await page.goto('/audit?id=1')
  await expect(page.getByRole('columnheader', { name: 'Data lama' })).toBeVisible()
  await expect(page.getByRole('columnheader', { name: 'Data baru' })).toBeVisible()
  await expect(page.getByText('~ Diubah').first()).toBeVisible()
})
test('respons API 403 menampilkan alasan tanpa akses', async ({ page }) => {
  await page.goto('/pengguna')
  await expect(page.getByRole('heading', { name: 'Pengguna', exact: true })).toBeVisible()
  await page.evaluate(() => {
    const user = JSON.parse(sessionStorage.getItem('simris-session')!)
    sessionStorage.setItem('simris-session', JSON.stringify({ ...user, role: 'Auditor' }))
  })
  await page.getByRole('link', { name: 'Katalog', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Anda tidak memiliki akses' })).toBeVisible()
  await expect(
    page.getByText('Role super admin aktif diperlukan untuk mengakses data ini.'),
  ).toBeVisible()
})
