import { Check, Minus } from 'lucide-react'
import { statusLabels } from '../lib/status'
import s from './StatusBadge.module.css'
export function StatusBadge({ status }: { status: string }) {
  const config = statusLabels[status]
  return (
    <span className={`${s.badge} ${config?.marked ? s.marked : ''}`}>
      {config?.marked ? (
        <Check size={12} aria-hidden="true" />
      ) : (
        <Minus size={12} aria-hidden="true" />
      )}
      {config?.label || status}
    </span>
  )
}
