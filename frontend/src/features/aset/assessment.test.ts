import { expect, it } from 'vitest'
import { isAssetPendingAssessment } from './assessment'

it.each([true, 1, '1', null, undefined])(
  'menganggap perlu_ditinjau=%s sebagai belum dinilai',
  (perlu_ditinjau) => {
    expect(isAssetPendingAssessment({ perlu_ditinjau })).toBe(true)
  },
)

it.each([false, 0, '0'])('menganggap perlu_ditinjau=%s sebagai sudah dinilai', (perlu_ditinjau) => {
  expect(isAssetPendingAssessment({ perlu_ditinjau })).toBe(false)
})

it('memetakan status teks penilaian tanpa bergantung pada flag boolean', () => {
  expect(isAssetPendingAssessment({ status_penilaian: 'belum_dinilai' })).toBe(true)
  expect(isAssetPendingAssessment({ status: 'belum-dinilai' })).toBe(true)
  expect(isAssetPendingAssessment({ perlu_ditinjau: true, status_penilaian: 'sudah_dinilai' })).toBe(
    false,
  )
})
