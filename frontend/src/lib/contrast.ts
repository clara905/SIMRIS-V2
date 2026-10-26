export function luminance(color: string) {
  const hex = /^#[0-9a-f]{6}$/i.test(color) ? color.slice(1) : 'd5dce0'
  const rgb = [0, 2, 4]
    .map((start) => parseInt(hex.slice(start, start + 2), 16) / 255)
    .map((v) => (v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4))
  return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722
}
export function contrastText(color: string) {
  return (luminance(color) + 0.05) / 0.05 >= 1.05 / (luminance(color) + 0.05)
    ? '#000000'
    : '#ffffff'
}
