import { fileURLToPath, URL } from 'node:url'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://127.0.0.1:8000',
      '/sanctum': 'http://127.0.0.1:8000',
      '/login': 'http://127.0.0.1:8000',
      '/logout': 'http://127.0.0.1:8000',
      '/daftar': 'http://127.0.0.1:8000',
      '/ganti-password': 'http://127.0.0.1:8000',
    },
  },
  test: {
    exclude: ['**/node_modules/**', '**/e2e/**'],
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
  },
})
