import { expect, it } from 'vitest'
import { encodeCsv, parseCsv } from './csv'
it('CSV mempertahankan koma, kutip, Unicode, dan baris baru dalam kolom', () => {
  const rows = [
    ['kode', 'nama', 'deskripsi'],
    ['A-1', 'Kontrol, baru', 'Baris satu\nKata "uji"'],
  ]
  expect(parseCsv(encodeCsv(rows))).toEqual(rows)
})
it('menolak kutip tak tertutup dan mengamankan formula saat ekspor', () => {
  expect(() => parseCsv('a,"b')).toThrow()
  expect(encodeCsv([['=1+1']])).toContain("'=1+1")
})
