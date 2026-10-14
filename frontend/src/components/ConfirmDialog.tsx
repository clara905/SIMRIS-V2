import { useState } from 'react'
import { AlertDialog } from 'radix-ui'
import s from './ConfirmDialog.module.css'
export function ConfirmDialog({
  open,
  onClose,
  title,
  description,
  confirmLabel,
  confirmation,
  pending,
  onConfirm,
}: {
  open: boolean
  onClose: () => void
  title: string
  description: string[]
  confirmLabel: string
  confirmation?: string
  pending?: boolean
  onConfirm: () => void | Promise<void>
}) {
  const [typed, setTyped] = useState('')
  return (
    <AlertDialog.Root
      open={open}
      onOpenChange={(value) => {
        if (!value && !pending) {
          setTyped('')
          onClose()
        }
      }}
    >
      <AlertDialog.Portal>
        <AlertDialog.Overlay className={s.overlay} />
        <AlertDialog.Content className={s.dialog}>
          <AlertDialog.Title>{title}</AlertDialog.Title>
          <AlertDialog.Description asChild>
            <div className={s.description}>
              <ul>
                {description.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </div>
          </AlertDialog.Description>
          {confirmation && (
            <>
              <label htmlFor="confirm-name">Ketik “{confirmation}” untuk melanjutkan</label>
              <input
                id="confirm-name"
                autoComplete="off"
                value={typed}
                onChange={(e) => setTyped(e.target.value)}
              />
            </>
          )}
          <div className={s.actions}>
            <AlertDialog.Cancel asChild>
              <button disabled={pending}>Batal</button>
            </AlertDialog.Cancel>
            <button
              className="primary"
              disabled={pending || (!!confirmation && typed !== confirmation)}
              onClick={async () => {
                await onConfirm()
                setTyped('')
              }}
            >
              {pending ? 'Memproses…' : confirmLabel}
            </button>
          </div>
        </AlertDialog.Content>
      </AlertDialog.Portal>
    </AlertDialog.Root>
  )
}
