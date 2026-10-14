export const statusLabels: Record<string, { label: string; marked?: boolean }> = {
  aktif: { label: 'Aktif', marked: true },
  nonaktif: { label: 'Nonaktif' },
  draf: { label: 'Draf' },
  dibekukan: { label: 'Dibekukan', marked: true },
  selesai: { label: 'Selesai', marked: true },
  dalam_penilaian: { label: 'Dalam penilaian' },
  ditinjau: { label: 'Ditinjau' },
  bermasalah: { label: 'Perlu diperiksa' },
  tersinkron: { label: 'Tersinkron', marked: true },
}
