import type { ReactNode } from 'react'
import { Tabs } from 'radix-ui'
import s from './TabBar.module.css'
export function TabBar({
  items,
  value,
  onChange,
  children,
  label = 'Bagian halaman',
}: {
  items: { value: string; label: string }[]
  value: string
  onChange: (value: string) => void
  children: ReactNode
  label?: string
}) {
  return (
    <Tabs.Root value={value} onValueChange={onChange}>
      <Tabs.List className={s.list} aria-label={label}>
        {items.map((item) => (
          <Tabs.Trigger className={s.tab} key={item.value} value={item.value}>
            {item.label}
          </Tabs.Trigger>
        ))}
      </Tabs.List>
      <Tabs.Content value={value}>{children}</Tabs.Content>
    </Tabs.Root>
  )
}
