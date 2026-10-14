import { describe, expect, it } from 'vitest'
import { methodIssues } from './metode'
import type { Metode } from '../api/types'
const valid: Metode = {
  id: 1,
  nama: 'Metode uji',
  deskripsi: '',
  status: 'draf',
  dibekukan_pada: null,
  kemungkinan: [{ nilai: 1, nama: 'Jarang', kriteria: 'Tahunan' }],
  dampak: [{ nilai: 1, nama: 'Ringan', kriteria: 'Layanan pulih' }],
  tingkat: [{ id: 1, nama: 'Rendah', warna: '#2e8b7a' }],
  kriteria: [{ id: 1, area: 'Operasional', dampak: 1, kriteria: 'Layanan pulih' }],
  matriks: [{ kemungkinan: 1, dampak: 1, skor: 1, tingkat_id: 1 }],
}
describe('kelengkapan sebelum pembekuan', () => {
  it('menerima metode lengkap dan menolak matriks duplikat atau skor keliru', () => {
    expect(methodIssues(valid)).toEqual([])
    expect(
      methodIssues({ ...valid, matriks: [valid.matriks[0], valid.matriks[0]] }).some(
        (i) => i.tab === 'matriks',
      ),
    ).toBe(true)
    expect(
      methodIssues({ ...valid, matriks: [{ ...valid.matriks[0], skor: 2 }] }).some(
        (i) => i.tab === 'matriks',
      ),
    ).toBe(true)
  })
  it('memerlukan satu kriteria per area dan skala dampak', () => {
    const incomplete = {
      ...valid,
      dampak: [...valid.dampak, { nilai: 2, nama: 'Besar', kriteria: 'Terhenti' }],
    }
    expect(methodIssues(incomplete).some((i) => i.tab === 'kriteria')).toBe(true)
    expect(
      methodIssues({
        ...valid,
        kriteria: [...valid.kriteria, { ...valid.kriteria[0], id: 2 }],
      }).some((i) => i.tab === 'kriteria'),
    ).toBe(true)
  })
})
