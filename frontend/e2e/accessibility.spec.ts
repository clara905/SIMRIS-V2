import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { demoSession } from './helpers'
const pages = [
  '/masuk',
  '/tanpa-akses',
  '/',
  '/pengguna',
  '/unit',
  '/pegawai',
  '/metode-risiko',
  '/metode-risiko/2',
  '/metode-risiko/1',
  '/katalog',
  '/standar',
  '/standar?tab=kontrol',
  '/aset',
  '/aset?tab=kategori',
  '/periode',
  '/monitoring',
  '/monitoring/asesmen/1',
  '/audit',
  '/dev/komponen',
]
for (const path of pages)
  test(`axe: ${path}`, async ({ page }) => {
    await demoSession(page)
    await page.goto(path)
    await page.waitForLoadState('networkidle')
    const result = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'])
      .analyze()
    expect(
      result.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        nodes: v.nodes.map((n) => n.target),
      })),
    ).toEqual([])
  })
test('pintasan, navigasi ponsel, zoom 200%, dan overflow halaman', async ({ page }) => {
  await demoSession(page)
  await page.goto('/pengguna')
  await page.getByRole('heading', { name: 'Pengguna', exact: true }).waitFor()
  await page.keyboard.press('/')
  await expect(page.getByLabel('Pencarian')).toBeFocused()
  await page.keyboard.type('g m')
  await expect(page).toHaveURL(/pengguna/)
  await page.keyboard.press('Tab')
  await page.locator('h1').click()
  await page.keyboard.press('g')
  await page.keyboard.press('m')
  await expect(page.getByRole('heading', { name: 'Metode risiko', exact: true })).toBeVisible()
  await page.keyboard.press('?')
  await expect(page.getByRole('dialog', { name: 'Pintasan keyboard' })).toBeVisible()
  await page.keyboard.press('Escape')
  for (const width of [390, 360, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 1000 })
    await page.goto('/metode-risiko/2')
    await page.waitForLoadState('networkidle')
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true)
  }
  await page.setViewportSize({ width: 390, height: 844 })
  await page.getByRole('button', { name: 'Buka navigasi' }).click()
  await page.getByRole('dialog').getByRole('link', { name: 'Pengguna', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Pengguna', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Tambah pengguna', exact: true }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  const box = await page.getByRole('dialog').boundingBox()
  expect(box?.width).toBe(390)
  await page.keyboard.press('Escape')
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.evaluate(() => {
    document.documentElement.style.zoom = '2'
  })
  await page.getByRole('button', { name: 'Tambah pengguna', exact: true }).click()
  await expect(page.getByLabel('Nama lengkap')).toBeVisible()
})
