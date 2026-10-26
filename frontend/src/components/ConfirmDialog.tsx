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
  passwordConfirmation,
  pending,
  onConfirm,
}: {
  open: boolean
  onClose: () => void
  title: string
  description: string[]
  confirmLabel: string
  confirmation?: string
  passwordConfirmation?: { value: string; onChange: (value: string) => void; error?: string }
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
          {passwordConfirmation && (
            <div>
              <label htmlFor="confirm-password">Konfirmasi kata sandi Anda</label>
              <input
                id="confirm-password"
                type="password"
                autoComplete="current-password"
                value={passwordConfirmation.value}
                onChange={(event) => passwordConfirmation.onChange(event.target.value)}
              />
              {passwordConfirmation.error && <p role="alert">{passwordConfirmation.error}</p>}
            </div>
          )}
          <div className={s.actions}>
            <AlertDialog.Cancel asChild>
              <button disabled={pending}>Batal</button>
            </AlertDialog.Cancel>
            <button
              className="primary"
              disabled={pending || (!!confirmation && typed !== confirmation) || (!!passwordConfirmation && !passwordConfirmation.value)}
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
