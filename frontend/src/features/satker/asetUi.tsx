import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { ReactNode } from 'react'
import type { Option } from './api'
import { humanize, text } from './asetUtils'
import './aset.css'

type Tone = 'ok' | 'warn' | 'bad' | 'mute'

export function Pill({ tone, children }: { tone: Tone; children: ReactNode }) {
  return <span className={'as-pill is-' + tone}>{children}</span>
}

const kondisiTone = (value: string): Tone =>
  value === 'BAIK' ? 'ok' : value === 'RUSAK_BIASA' ? 'warn' : value === 'RUSAK_BERAT' ? 'bad' : 'mute'

export function KondisiPill({ value }: { value: unknown }) {
  const v = text(value)
  return v ? <Pill tone={kondisiTone(v)}>{humanize(v)}</Pill> : <span className="as-sub">-</span>
}

export function StatusPill({ value }: { value: unknown }) {
  const v = text(value)
  return v ? <Pill tone={v === 'AKTIF' ? 'ok' : 'mute'}>{humanize(v)}</Pill> : <span className="as-sub">-</span>
}

export function Field({
  label,
  hint,
  error,
  required,
  wide,
  children,
}: {
  label: string
  hint?: string
  error?: string
  required?: boolean
  wide?: boolean
  children: ReactNode
}) {
  return (
    <div className={'as-field' + (wide ? ' is-wide' : '')}>
      <label>
        <span className="as-label">
          {label}
          {required && <span className="as-req" aria-hidden="true"> *</span>}
        </span>
        {children}
      </label>
      {hint && !error && <small className="as-hint">{hint}</small>}
      {error && <small role="alert" className="as-error">{error}</small>}
    </div>
  )
}

export function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="as-section">
      <h3>{title}</h3>
      {children}
    </section>
  )
}

export function SelectBox({
  value,
  onChange,
  options,
  placeholder,
  disabled,
  required,
  label,
}: {
  value: string
  onChange: (value: string) => void
  options: Option[]
  placeholder: string
  disabled?: boolean
  required?: boolean
  label?: string
}) {
  return (
    <select aria-label={label} value={value} required={required} disabled={disabled} onChange={(e) => onChange(e.target.value)}>
      <option value="">{placeholder}</option>
      {options.map((o) => (
        <option key={o.id} value={String(o.id)}>{o.label}</option>
      ))}
    </select>
  )
}

type Meta = { from?: number | null; to?: number | null; total: number; current_page: number; last_page: number }

export function Pager({ meta, onPage }: { meta: Meta; onPage: (page: number) => void }) {
  return (
    <div className="as-pager">
      <span aria-live="polite">{meta.from || 0}-{meta.to || 0} dari {meta.total} data</span>
      <div>
        <button type="button" className="as-icon-btn" aria-label="Halaman sebelumnya" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
          <ChevronLeft size={16} />
        </button>
        <span>Halaman {meta.current_page} / {meta.last_page}</span>
        <button type="button" className="as-icon-btn" aria-label="Halaman berikutnya" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)}>
          <ChevronRight size={16} />
        </button>
      </div>
    </div>
  )
}
