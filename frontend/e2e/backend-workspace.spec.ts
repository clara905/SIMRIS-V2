import { test, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
const rows = Array.from({ length: 12 }, (_, i) => ({
  id: i + 1,
  tujuan: 'Pengamanan layanan informasi ' + (i + 1),
  ruang_lingkup: 'Layanan organisasi',
  unit_id: 2,
  periode_id: 1,
  nomor_versi: 1,
  status: i % 2 ? 'DISAHKAN' : 'DRAFT',
}))
async function setup(page: Page, role = 'SUPER_ADMIN', password = false) {
  await page.route('**/api/**', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname
    if (!path.startsWith('/api/')) return route.fallback()
    if (path === '/api/me')
      return route.fulfill({
        json: {
          data: {
            user: {
              id: 1,
              username: 'penguji',
              email_login: 'penguji@example.test',
              wajib_ganti_password: password,
            },
            pegawai: { nama: 'Petugas Pengujian' },
            roles: [{ kode: role, nama: role, unit_id: 2 }],
          },
        },
      })
    let data: Record<string, unknown>[] = path.includes('/asesmen')
      ? rows
      : path === '/api/aset'
        ? [
            {
              id: 1,
              kode_aset: 'AST-001',
              nama: 'Server pengujian',
              unit_pemilik_id: 2,
              kondisi: 'BAIK',
              status: 'AKTIF',
              perlu_ditinjau: true,
            },
            {
              id: 2,
              kode_aset: 'AST-002',
              nama: 'Workstation analis',
              unit_pemilik_id: 2,
              kondisi: 'BAIK',
              status: 'AKTIF',
              perlu_ditinjau: false,
            },
          ]
        : []
    if (path.includes('/risiko') || path === '/api/laporan/lampiran-14')
      data = [{ id: 5, kode: 'R-001', skenario: 'Gangguan layanan informasi' }]
    const id = url.searchParams.get('filter[id]')
    const status = url.searchParams.get('filter[status]')
    const perluDitinjau = url.searchParams.get('filter[perlu_ditinjau]')
    if (id) data = data.filter((r) => String(r.id) === id)
    if (status) data = data.filter((r) => r.status === status)
    if (perluDitinjau !== null)
      data = data.filter((r) => String(Boolean(r.perlu_ditinjau)) === perluDitinjau)
    const current = Number(url.searchParams.get('page') || 1)
    const size = Number(url.searchParams.get('per_page') || 10)
    const slice = data.slice((current - 1) * size, current * size)
    return route.fulfill({
      json: {
        data: slice,
        meta: {
          current_page: current,
          last_page: Math.max(1, Math.ceil(data.length / size)),
          total: data.length,
          from: slice.length ? (current - 1) * size + 1 : null,
          to: slice.length ? Math.min(current * size, data.length) : null,
        },
      },
    })
  })
}
test('dashboard uses backend totals, has accessible desktop and mobile layouts', async ({
  page,
}) => {
  await setup(page)
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Ringkasan ruang kerja' })).toBeVisible()
  await expect(page.locator('.ws-metric').first().locator('strong')).toHaveText('12')
  await page.screenshot({ path: 'test-results/backend-desktop.png', fullPage: true })
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
    .analyze()
  expect(
    results.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target) })),
  ).toEqual([])
  for (const width of [390, 768, 1024]) {
    await page.setViewportSize({ width, height: 900 })
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  }
  await page.setViewportSize({ width: 390, height: 844 })
  await page.getByRole('button', { name: 'Buka navigasi' }).click()
  await page.getByRole('dialog').getByRole('link', { name: 'Dokumen & bukti' }).click()
  await expect(page.getByRole('heading', { name: 'Dokumen & bukti' })).toBeVisible()
  await page.screenshot({ path: 'test-results/backend-mobile.png', fullPage: true })
})
test('register sends server pagination and filters, opens risks and report', async ({ page }) => {
  await setup(page)
  await page.goto('/monitoring')
  await expect(page.getByText('1-10 dari 12 data')).toBeVisible()
  await page.getByRole('button', { name: 'Halaman berikutnya' }).click()
  await expect(page.getByText('11-12 dari 12 data')).toBeVisible()
  await page.getByLabel('Filter status').selectOption('DISAHKAN')
  await expect(page.getByText('1-6 dari 6 data')).toBeVisible()
  await page.getByRole('button', { name: 'Lihat rincian 2', exact: true }).click()
  await page.getByRole('button', { name: 'Risiko', exact: true }).click()
  await expect(page.locator('summary').filter({ hasText: 'R-001' })).toBeVisible()
  await page.getByRole('button', { name: 'Lampiran 14', exact: true }).click()
  await expect(page.getByText('Data Lampiran 14 tersedia.', { exact: false })).toBeVisible()
  await page.keyboard.press('Escape')
  await page.getByLabel('Cari berdasarkan ID').fill('4')
  await page.getByRole('button', { name: 'Cari', exact: true }).click()
  await expect(page.getByText('1-1 dari 1 data')).toBeVisible()
})
test('staff can enter workspace but cannot open administration', async ({ page }) => {
  await setup(page, 'STAFF')
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Ringkasan ruang kerja' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Pengguna & akses' })).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Jejak audit' })).toHaveCount(0)
  await page.goto('/pengguna')
  await expect(page.getByRole('heading', { name: 'Halaman tidak tersedia' })).toBeVisible()
})
test('sidebar profile link opens the signed-in user profile', async ({ page }) => {
  await setup(page)
  await page.goto('/')
  await expect(page.getByText('Akses sesuai kewenangan')).toHaveCount(0)
  await expect(page.getByText('Lingkup unit mengikuti role aktif')).toHaveCount(0)
  await page.getByRole('link', { name: 'Buka profil Petugas Pengujian' }).click()
  await expect(page).toHaveURL(/\/profil$/)
  await expect(page.getByRole('heading', { name: 'Profil' })).toBeVisible()
  await expect(page.getByText('penguji@example.test')).toBeVisible()
  const profile = page.getByRole('region', { name: 'Informasi profil' })
  await expect(profile.getByRole('definition').filter({ hasText: 'SUPER_ADMIN' })).toBeVisible()
  await expect(profile.getByRole('definition').filter({ hasText: 'Unit 2' })).toBeVisible()
})
test('logout requires confirmation before ending the session', async ({ page }) => {
  await setup(page)
  let logoutRequests = 0
  await page.route('**/logout', (route) => {
    logoutRequests += 1
    return route.fulfill({ json: { message: 'OK' } })
  })
  await page.goto('/')

  await page.getByRole('button', { name: 'Keluar', exact: true }).click()
  const confirmation = page.getByRole('alertdialog')
  await expect(confirmation).toBeVisible()
  expect(logoutRequests).toBe(0)
  await confirmation.getByRole('button', { name: 'Batal' }).click()
  await expect(confirmation).toBeHidden()
  expect(logoutRequests).toBe(0)

  await page.getByRole('button', { name: 'Keluar', exact: true }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Ya, keluar' }).click()
  await expect(page).toHaveURL(/masuk/)
  expect(logoutRequests).toBe(1)
})
test('login sends backend login and optional TOTP, password change sends current password', async ({
  page,
}) => {
  await setup(page, 'STAFF', true)
  await page.route('**/sanctum/csrf-cookie', (r) => r.fulfill({ status: 204 }))
  let payload: Record<string, unknown> = {}
  await page.route('**/login', (r) => {
    payload = r.request().postDataJSON()
    return r.fulfill({ json: { message: 'OK' } })
  })
  await page.goto('/masuk')
  await page.getByLabel('Username atau email').fill('penguji')
  await page.getByLabel('Kata sandi', { exact: true }).fill('SandiPengujian123!')
  await page.getByLabel('Kode autentikator').fill('123456')
  await page.getByRole('button', { name: 'Masuk ke ruang kerja' }).click()
  await expect(page).toHaveURL(/ganti-password/)
  expect(payload).toEqual({ login: 'penguji', password: 'SandiPengujian123!', totp: '123456' })
  let change: Record<string, unknown> = {}
  await page.route('**/api/ganti-password', (r) => {
    change = r.request().postDataJSON()
    return r.fulfill({
      status: 422,
      json: {
        message: 'Validasi pengujian',
        errors: { current_password: ['Password saat ini tidak sesuai.'] },
      },
    })
  })
  await page.getByLabel('Kata sandi saat ini').fill('SandiLama123!')
  await page.getByLabel('Kata sandi baru', { exact: true }).fill('SandiBaru12345!')
  await page.getByLabel('Konfirmasi kata sandi baru').fill('SandiBaru12345!')
  await page.getByRole('button', { name: 'Simpan kata sandi' }).click()
  await expect(page.getByText('Password saat ini tidak sesuai.')).toBeVisible()
  expect(change.current_password).toBe('SandiLama123!')
})
test('API failures can be retried and empty state is explicit', async ({ page }) => {
  await setup(page)
  await page.route('**/api/aset**', (r) =>
    r.fulfill({ status: 500, json: { message: 'Sumber belum tersedia' } }),
  )
  await page.goto('/aset')
  await expect(page.getByText('Sumber belum tersedia')).toBeVisible()
  await page.unroute('**/api/aset**')
  await page.getByRole('button', { name: 'Coba lagi' }).click()
  await expect(page.getByText('Server pengujian')).toBeVisible()
  await page.getByLabel('Cari berdasarkan ID').fill('99')
  await page.getByRole('button', { name: 'Cari', exact: true }).click()
  await expect(page.getByText('Tidak ada data yang ditemukan')).toBeVisible()
})
