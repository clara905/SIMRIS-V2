import type { Page } from '@playwright/test'
export async function demoSession(page: Page) {
  await page.addInitScript(() => {
    if (!sessionStorage.getItem('simris-session'))
      sessionStorage.setItem(
        'simris-session',
        JSON.stringify({
          id: 1,
          nama: 'Aditya Pratama',
          email: 'admin@simris.go.id',
          role: 'Super admin',
          wajib_ganti_password: false,
        }),
      )
  })
}
