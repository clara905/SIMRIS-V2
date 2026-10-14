import { BadgeCheck } from 'lucide-react'
import { tanggal } from '../lib/format'
import s from './SegelBeku.module.css'
export function SegelBeku({ date, animated = false }: { date: string; animated?: boolean }) {
  return (
    <div
      className={`${s.seal} ${animated ? s.animated : ''}`}
      aria-label={`Dibekukan ${tanggal(date)}`}
    >
      <BadgeCheck size={26} aria-hidden="true" />
      <div>
        <strong>Dibekukan</strong>
        <small>{tanggal(date)}</small>
      </div>
    </div>
  )
}
