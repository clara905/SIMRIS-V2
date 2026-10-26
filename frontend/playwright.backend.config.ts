import { defineConfig } from '@playwright/test'
export default defineConfig({
  testDir: './e2e',
  testMatch: 'backend-workspace.spec.ts',
  workers: 1,
  use: {
    baseURL: 'http://127.0.0.1:5175',
    headless: true,
    viewport: { width: 1440, height: 1050 },
    reducedMotion: 'reduce',
  },
  webServer: {
    command: 'npm run dev -- --host 127.0.0.1 --port 5175 --strictPort',
    url: 'http://127.0.0.1:5175',
    reuseExistingServer: true,
    env: { VITE_API_MOCK: 'false', VITE_API_URL: 'http://127.0.0.1:5175' },
  },
  reporter: 'list',
})
