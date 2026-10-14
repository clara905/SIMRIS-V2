import type { ReactNode } from 'react'
import { Archive, CircleAlert } from 'lucide-react'
import s from './States.module.css'
export function EmptyState({
  title,
  description,
  action,
}: {
  title: string
  description: string
  action?: ReactNode
}) {
  return (
    <div className={s.state}>
      <Archive size={28} />
      <h2>{title}</h2>
      <p>{description}</p>
      {action}
    </div>
  )
}
export function ErrorState({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <div className={s.state} role="alert">
      <CircleAlert size={28} />
      <h2>Data belum dapat dimuat</h2>
      <p>{message}</p>
      <button onClick={onRetry}>Coba lagi</button>
    </div>
  )
}
export function Skeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className={s.skeleton} role="status" aria-label="Memuat data">
      <span className="sr-only">Memuat data…</span>
      {Array.from({ length: rows }, (_, i) => (
        <div key={i} />
      ))}
    </div>
  )
}
