import { useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Dialog } from 'radix-ui'
import {
  PanelLeftClose,
  PanelLeftOpen,
  Menu,
  X,
  LogOut,
  ChevronRight,
} from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { navigation } from '../lib/navigation'
import { logout, useSession } from '../api/auth'
import { errorMessage } from '../api/client'
import { ConfirmDialog } from './ConfirmDialog'
import s from './AppShell.module.css'
import { KeyboardShortcuts } from './KeyboardShortcuts'

export function AppShell() {
  const [collapsed, setCollapsed] = useState(
    () => localStorage.getItem('simris-nav') === 'collapsed',
  )
  const [mobile, setMobile] = useState(false)
  const [leaving, setLeaving] = useState(false)
  const [logoutConfirm, setLogoutConfirm] = useState(false)
  const location = useLocation()
  const navigate = useNavigate()
  const client = useQueryClient()
  const { data: user } = useSession()
  const title =
    navigation.find((item) => item.path !== '/' && location.pathname.startsWith(item.path))
      ?.label || (location.pathname === '/dev/komponen' ? 'Galeri komponen' : 'Beranda')
  const nav = (compact: boolean) => (
    <nav aria-label="Navigasi utama" className={s.nav}>
      {navigation.map((item, index) => (
        <div key={item.path}>
          {(!index || item.group !== navigation[index - 1].group) && !compact && (
            <p className={s.group}>{item.group}</p>
          )}
          <NavLink
            to={item.path}
            end={item.path === '/'}
            title={compact ? item.label : undefined}
            aria-label={compact ? item.label : undefined}
            onClick={() => setMobile(false)}
          >
            <item.icon size={18} aria-hidden="true" />
            {!compact && item.label}
          </NavLink>
        </div>
      ))}
    </nav>
  )
  return (
    <div className={`${s.shell} ${collapsed ? s.collapsed : ''}`}>
      <a className={s.skip} href="#konten">
        Lewati ke konten
      </a>
      <aside className={s.sidebar} aria-label="Menu aplikasi">
        <div className={s.brand}>
          <span className={s.brandMark}>
            <img src="/logo-kemhan.png" alt="Logo Kemhan" style={{ width: 36, height: 36, objectFit: 'contain' }} />
          </span>
          {!collapsed && (
            <div>
              <strong>SIMRIS</strong>
              <small>Manajemen risiko instansi</small>
            </div>
          )}
        </div>
        {nav(collapsed)}
        <div className={s.sidebarFoot}>
          <button
            aria-label={collapsed ? 'Bentangkan navigasi' : 'Ciutkan navigasi'}
            onClick={() => {
              setCollapsed(!collapsed)
              localStorage.setItem('simris-nav', !collapsed ? 'collapsed' : 'expanded')
            }}
          >
            {collapsed ? <PanelLeftOpen size={18} /> : <PanelLeftClose size={18} />}
            {!collapsed && 'Ciutkan navigasi'}
          </button>
          {!collapsed && <span>Ruang kerja super admin</span>}
        </div>
      </aside>
      <div className={s.workspace}>
        <header className={s.topbar}>
          <div className={s.breadcrumb}>
            <Dialog.Root open={mobile} onOpenChange={setMobile}>
              <Dialog.Trigger
                className={`icon-button ${s.mobileToggle}`}
                aria-label="Buka navigasi"
              >
                <Menu size={20} />
              </Dialog.Trigger>
              <Dialog.Portal>
                <Dialog.Overlay className={s.overlay} />
                <Dialog.Content className={s.mobileNav}>
                  <Dialog.Close className={`icon-button ${s.close}`} aria-label="Tutup navigasi">
                    <X size={20} />
                  </Dialog.Close>
                  <Dialog.Title>SIMRIS</Dialog.Title>
                  <Dialog.Description>Ruang kerja super admin</Dialog.Description>
                  {nav(false)}
                </Dialog.Content>
              </Dialog.Portal>
            </Dialog.Root>
            <span>Administrasi</span>
            <ChevronRight size={14} aria-hidden="true" />
            <span>{title}</span>
          </div>
          <div className={s.account}>
            <KeyboardShortcuts />
            <span className={s.avatar} aria-hidden="true">
              {user?.nama
                .split(' ')
                .slice(0, 2)
                .map((n) => n[0])
                .join('')}
            </span>
            <span className={s.accountText}>
              {user?.nama}
              <small>{user?.role}</small>
            </span>
            <button
              className="icon-button"
              aria-label="Keluar"
              disabled={leaving}
              onClick={() => setLogoutConfirm(true)}
            >
              <LogOut size={18} />
            </button>
          </div>
        </header>
        <main id="konten" className={s.main} tabIndex={-1}>
          <Outlet />
          <footer className={s.footer}>
            <span>SIMRIS · Sistem informasi manajemen risiko</span>
            <span>
              {import.meta.env.VITE_API_MOCK === 'true'
                ? 'Lingkungan demonstrasi · Data simulasi'
                : 'Ruang kerja administrasi'}
            </span>
          </footer>
        </main>
      </div>
      <ConfirmDialog
        open={logoutConfirm}
        onClose={() => setLogoutConfirm(false)}
        title="Konfirmasi keluar"
        description={['Apakah Anda yakin ingin keluar dari akun ini?']}
        confirmLabel="Ya, keluar"
        pending={leaving}
        onConfirm={async () => {
          setLeaving(true)
          try {
            await logout()
            client.clear()
            navigate('/masuk', { replace: true })
          } catch (error) {
            toast.error(errorMessage(error))
          } finally {
            setLeaving(false)
          }
        }}
      />
    </div>
  )
}
