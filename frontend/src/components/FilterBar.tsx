import type { ReactNode } from 'react'
import { Search } from 'lucide-react'
import s from './FilterBar.module.css'
export function FilterBar({
  value,
  onChange,
  placeholder = 'Cari nama atau kode…',
  children,
}: {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  children?: ReactNode
}) {
  return (
    <div className={s.bar}>
      <div className={s.search}>
        <Search size={17} aria-hidden="true" />
        <input
          aria-label="Pencarian"
          data-search
          type="search"
          placeholder={placeholder}
          value={value}
          onChange={(e) => onChange(e.target.value)}
        />
      </div>
      {children}
    </div>
  )
}
