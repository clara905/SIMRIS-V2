import { chromium } from '@playwright/test'
import { mkdir } from 'node:fs/promises'
const stage = process.argv[2] || 'baseline'
const paths = process.argv.slice(3)
const browser = await chromium.launch({ headless: true })
await mkdir(`artifacts/${stage}`, { recursive: true })
for (const width of [1440, 1024, 390]) {
  const page = await browser.newPage({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' })
  if (stage !== 'tahap-0') {
    await page.addInitScript(() =>
      sessionStorage.setItem(
        'simris-session',
        JSON.stringify({
          id: 1,
          nama: 'Aditya Pratama',
          email: 'admin@simris.go.id',
          role: 'Super admin',
          wajib_ganti_password: false,
        }),
      ),
    )
  }
  for (const path of paths.length ? paths : ['/']) {
    await page.goto(`http://127.0.0.1:5173${path}`)
    await page.waitForLoadState('networkidle')
    await page.screenshot({
      path: `artifacts/${stage}/${path.replace(/\W+/g, '-') || 'home'}-${width}.png`,
      fullPage: true,
    })
  }
  await page.close()
}
await browser.close()
