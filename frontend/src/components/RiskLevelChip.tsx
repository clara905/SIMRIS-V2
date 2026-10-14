import s from './RiskLevelChip.module.css'
import { contrastText } from '../lib/contrast'
export function RiskLevelChip({ name, color }: { name: string; color: string }) {
  return (
    <span className={s.chip} style={{ background: color, color: contrastText(color) }}>
      {name}
    </span>
  )
}
