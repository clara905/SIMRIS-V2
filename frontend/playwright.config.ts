import { defineConfig } from '@playwright/test'
export default defineConfig({
  testDir: './e2e',
  testIgnore: 'backend-workspace.spec.ts',
  fullyParallel: false,
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:5173',
    headless: true,
    viewport: { width: 1440, height: 1000 },
    reducedMotion: 'reduce',
  },
  reporter: 'list',
  timeout: 30_000,
})
