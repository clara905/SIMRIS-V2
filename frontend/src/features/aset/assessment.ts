function normalizeAssessmentStatus(value: unknown): string {
  return typeof value === 'string'
    ? value.trim().toLowerCase().replace(/[\s-]+/g, '_')
    : ''
}

export function isAssetPendingAssessment(asset: Record<string, unknown>): boolean {
  const status = normalizeAssessmentStatus(asset.status_penilaian || asset.status)

  if (['belum_dinilai', 'pending', 'belum'].includes(status)) {
    return true
  }
  if (['sudah_dinilai', 'assessed', 'selesai'].includes(status)) {
    return false
  }

  const flag = asset.perlu_ditinjau
  if (flag === true || flag === 1 || flag === '1') {
    return true
  }
  if (flag === false || flag === 0 || flag === '0') {
    return false
  }

  return true
}
