import { test, expect } from '@playwright/test'
import { demoSession } from './helpers'
test('drawer dan konfirmasi dapat dipakai dengan keyboard', async ({ page }) => {
  await demoSession(page)
  await page.goto('/dev/komponen')
  await page.getByRole('button', { name: 'Buka rincian', exact: true }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).toBeHidden()
  await page.getByRole('button', { name: 'Konfirmasi pembekuan' }).click()
  const button = page.getByRole('button', { name: 'Bekukan versi', exact: true })
  await expect(button).toBeDisabled()
  await page.getByLabel('Ketik', { exact: false }).fill('Metode 2026')
  await button.click()
  await expect(page.getByText('Versi dibekukan', { exact: true })).toBeVisible()
})
