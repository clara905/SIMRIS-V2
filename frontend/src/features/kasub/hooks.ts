/** Query key review asesmen Kasub; endpoint dipasang setelah B1 tersedia. */
export const kasubReviewKeys = {
  root: ['kasub-review'] as const,
  assessments: (scope: 'menunggu-saya' | 'semua-unit') => [...kasubReviewKeys.root, 'asesmen', scope] as const,
}

export const statusAsesmenKasub = [
  { kode: 'DRAFT', label: 'Draft', mencakup: ['DRAFT', 'MENUNGGU_KASUB', 'RESIDUAL_DRAFT'] },
  { kode: 'MENUNGGU_KABID', label: 'Menunggu persetujuan Kabid', mencakup: ['MENUNGGU_KABID', 'VERIFIKASI_KABID'] },
  { kode: 'DISAHKAN', label: 'Disahkan', mencakup: ['DISAHKAN'] },
  { kode: 'DITOLAK', label: 'Ditolak', mencakup: ['DITOLAK'] },
  { kode: 'RESIDUAL', label: 'Residual', mencakup: ['PENANGANAN', 'VERIFIKASI_KASUB', 'MENUNGGU_KASATKER'] },
] as const

/** Mengubah status workflow ke kategori yang dipahami Kasub, tanpa memutasi data server. */
export function statusKasub(status: string): string {
  return statusAsesmenKasub.find((item) => item.mencakup.includes(status as never))?.label ?? 'Residual'
}
