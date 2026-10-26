import type { Unit } from '../api/types'
import type { TreeNode } from '../components/TreeView'
export function isActive(end: string | null, start = '2000-01-01') {
  const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date())
  return start <= today && (!end || end >= today)
}
export function unitTree(
  units: Unit[],
  parent: number | null = null,
  visited = new Set<number>(),
): TreeNode[] {
  return units
    .filter((u) => u.induk_id === parent && !visited.has(u.id))
    .map((u) => ({
      id: u.id,
      label: u.nama,
      children: unitTree(units, u.id, new Set([...visited, u.id])),
    }))
}
export function unitPath(units: Unit[], id: number): string[] {
  const result: string[] = []
  const seen = new Set<number>()
  let unit = units.find((u) => u.id === id)
  while (unit && !seen.has(unit.id)) {
    result.unshift(unit.nama)
    seen.add(unit.id)
    unit = units.find((u) => u.id === unit?.induk_id)
  }
  return result
}
