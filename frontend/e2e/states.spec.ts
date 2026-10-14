import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { demoSession } from './helpers'
import { mkdir } from 'node:fs/promises'
for (const width of [1440, 1024, 390])
  test(`keadaan form dan dialog pada ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 1000 })
    await mkdir('artifacts/tahap-7', { recursive: true })
    const inspect = async (name: string) => {
      const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
        .analyze()
      expect(
        result.violations.map((v) => ({ id: v.id, targets: v.nodes.map((n) => n.target) })),
      ).toEqual([])
      await page.screenshot({ path: `artifacts/tahap-7/${name}-${width}.png`, fullPage: true })
    }
    await demoSession(page)
    await page.goto('/pengguna')
    await page.getByRole('button', { name: 'Tambah pengguna', exact: true }).click()
    await page
      .getByRole('dialog')
      .getByRole('button', { name: 'Tambah pengguna', exact: true })
      .click()
    await expect(page.getByText('Nama lengkap wajib diisi.')).toBeVisible()
    await inspect('form-galat')
    await page.keyboard.press('Escape')
    await page.goto('/metode-risiko/2')
    await page.getByRole('button', { name: 'Bekukan versi', exact: true }).click()
    await page.getByRole('dialog').waitFor()
    await inspect('masalah-matriks')
    await page.keyboard.press('Escape')
    await page.goto('/dev/komponen')
    await page.getByRole('button', { name: 'Konfirmasi pembekuan' }).click()
    await page.getByRole('alertdialog').waitFor()
    await inspect('konfirmasi-beku')
    await page.keyboard.press('Escape')
    await page.goto('/audit?id=1')
    await page.getByRole('dialog').waitFor()
    await inspect('diff-audit')
    await page.keyboard.press('Escape')
    await page.evaluate(() => {
      const session = JSON.parse(sessionStorage.getItem('simris-session')!)
      session.wajib_ganti_password = true
      sessionStorage.setItem('simris-session', JSON.stringify(session))
    })
    await page.goto('/ganti-password')
    await page.getByRole('heading', { name: 'Ganti kata sandi' }).waitFor()
    await inspect('ganti-password')
  })
