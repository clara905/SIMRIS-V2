import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Keyboard } from 'lucide-react'
import { Drawer } from './Drawer'
import { navigation } from '../lib/navigation'
import s from './KeyboardShortcuts.module.css'
export function KeyboardShortcuts() {
  const [open, setOpen] = useState(false)
  const navigate = useNavigate()
  useEffect(() => {
    let prefix = 0
    const handler = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement
      if (
        event.ctrlKey ||
        event.metaKey ||
        event.altKey ||
        target.closest('input, textarea, select, [contenteditable=true]')
      )
        return
      if (document.querySelector('[role=dialog], [role=alertdialog]')) return
      if (event.key === '?') {
        event.preventDefault()
        setOpen(true)
        prefix = 0
        return
      }
      if (event.key === '/') {
        const search = document.querySelector<HTMLInputElement>('[data-search]')
        if (search) {
          event.preventDefault()
          search.focus()
        }
        prefix = 0
        return
      }
      if (prefix && Date.now() - prefix < 1500) {
        const item = navigation.find((n) => n.key === event.key.toLowerCase())
        if (item) {
          event.preventDefault()
          navigate(item.path)
        }
        prefix = 0
        return
      }
      prefix = event.key.toLowerCase() === 'g' ? Date.now() : 0
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [navigate])
  return (
    <>
      <button className={s.trigger} onClick={() => setOpen(true)} aria-label="Pintasan keyboard">
        <Keyboard size={16} />
        <span>Pintasan</span>
        <kbd>?</kbd>
      </button>
      <Drawer
        open={open}
        onClose={() => setOpen(false)}
        title="Pintasan keyboard"
        description="Pintasan tidak berjalan ketika Anda mengetik dalam form."
      >
        <dl className={s.list}>
          <dt>
            <kbd>/</kbd>
          </dt>
          <dd>Fokus ke pencarian</dd>
          <dt>
            <kbd>Esc</kbd>
          </dt>
          <dd>Tutup drawer atau dialog</dd>
          <dt>
            <kbd>?</kbd>
          </dt>
          <dd>Buka daftar pintasan</dd>
          {navigation.map((item) => (
            <div className={s.entry} key={item.key}>
              <dt>
                <kbd>g</kbd> lalu <kbd>{item.key}</kbd>
              </dt>
              <dd>{item.label}</dd>
            </div>
          ))}
        </dl>
      </Drawer>
    </>
  )
}
