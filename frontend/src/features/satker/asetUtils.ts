export const text = (value: unknown) => (value == null ? '' : String(value))

export const humanize = (value: unknown) => {
  const words = text(value)
    .trim()
    .toLowerCase()
    .split(/[_\s]+/)
    .filter(Boolean)

  return words.length
    ? words.map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
    : '-'
}

export const namaSaja = (label: string) => {
  const normalized = text(label).trim()
  const i = normalized.indexOf(' - ')
  return i >= 0 ? normalized.slice(i + 3) : normalized
}
