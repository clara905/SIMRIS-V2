import type { ReactNode } from 'react'
import { Dialog } from 'radix-ui'
import { X } from 'lucide-react'
import s from './Drawer.module.css'
export function Drawer({
  open,
  onClose,
  title,
  description = 'Rincian data yang dipilih.',
  children,
}: {
  open: boolean
  onClose: () => void
  title: string
  description?: string
  children: ReactNode
}) {
  return (
    <Dialog.Root
      open={open}
      onOpenChange={(v) => {
        if (!v) onClose()
      }}
    >
      <Dialog.Portal>
        <Dialog.Overlay className={s.overlay} />
        <Dialog.Content className={s.content}>
          <header className={s.header}>
            <Dialog.Title className={s.title}>{title}</Dialog.Title>
            <Dialog.Description className={s.description}>{description}</Dialog.Description>
            <Dialog.Close className={`icon-button ${s.close}`} aria-label="Tutup rincian">
              <X size={20} />
            </Dialog.Close>
          </header>
          <div className={s.body}>{children}</div>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  )
}
