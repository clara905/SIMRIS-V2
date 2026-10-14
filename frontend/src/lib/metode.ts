import type { Metode } from '../api/types'
export function methodIssues(method: Metode) {
  const issues: { tab: string; message: string }[] = []
  for (const tab of ['kemungkinan', 'dampak'] as const) {
    const scale = method[tab]
    if (
      !scale.length ||
      new Set(scale.map((s) => s.nilai)).size !== scale.length ||
      scale.some((s) => !s.nama.trim() || !s.kriteria.trim() || s.nilai <= 0)
    )
      issues.push({ tab, message: `Lengkapi skala ${tab} dengan nilai unik dan kriteria.` })
  }
  if (
    !method.tingkat.length ||
    method.tingkat.some((level) => !level.nama.trim() || !/^#[0-9a-f]{6}$/i.test(level.warna))
  )
    issues.push({ tab: 'tingkat', message: 'Lengkapi nama dan warna tingkat risiko.' })
  const areas = [...new Set(method.kriteria.map((k) => k.area.trim()))]
  if (!areas.length)
    issues.push({ tab: 'kriteria', message: 'Tambahkan area dan kriteria dampak.' })
  for (const area of areas) {
    if (
      !area ||
      method.dampak.some(
        (d) =>
          method.kriteria.filter(
            (k) => k.area.trim() === area && k.dampak === d.nilai && k.kriteria.trim(),
          ).length !== 1,
      )
    )
      issues.push({
        tab: 'kriteria',
        message: `Lengkapi satu kriteria untuk setiap skala dampak pada area ${area || '(tanpa nama)'}.`,
      })
  }
  if (
    method.matriks.length !== method.kemungkinan.length * method.dampak.length ||
    method.kemungkinan.some((k) =>
      method.dampak.some(
        (d) =>
          method.matriks.filter(
            (c) =>
              c.kemungkinan === k.nilai &&
              c.dampak === d.nilai &&
              c.skor === k.nilai * d.nilai &&
              method.tingkat.some((l) => l.id === c.tingkat_id),
          ).length !== 1,
      ),
    )
  )
    issues.push({
      tab: 'matriks',
      message: 'Lengkapi seluruh sel matriks sesuai skala yang berlaku.',
    })
  return issues
}
