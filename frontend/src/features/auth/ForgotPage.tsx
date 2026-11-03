import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { api, errorMessage } from '../../api/client'
import s from './Auth.module.css'

declare global { interface Window { grecaptcha?: { render: (el: HTMLElement, options: Record<string, unknown>) => number; ready: (fn: () => void) => void; reset: (id?: number) => void } } }

export function ForgotPage() {
  const [email, setEmail] = useState('')
  const [captcha, setCaptcha] = useState('')
  const [siteKey, setSiteKey] = useState(import.meta.env.VITE_RECAPTCHA_SITE_KEY || '')
  const [error, setError] = useState('')
  const [sent, setSent] = useState(false)
  const [sending, setSending] = useState(false)
  const element = useRef<HTMLDivElement>(null)
  const widget = useRef<number | null>(null)
  const token = useRef('')

  useEffect(() => {
    if (!siteKey) void api.get('/api/v1/config').then((response) => setSiteKey(response.data.recaptcha_site_key || '')).catch(() => setError('Konfigurasi reCAPTCHA tidak dapat dimuat.'))
  }, [siteKey])

  useEffect(() => {
    if (!siteKey || !element.current) return
    const render = () => {
      if (!window.grecaptcha || !element.current || widget.current !== null) return
      widget.current = window.grecaptcha.render(element.current, { sitekey: siteKey, callback: (value: string) => { token.current = value; setCaptcha(value) }, 'expired-callback': () => { token.current = ''; setCaptcha('') } })
    }
    if (window.grecaptcha) { window.grecaptcha.ready(render); return }
    let script = document.querySelector<HTMLScriptElement>('script[data-simris-recaptcha]')
    const load = () => window.grecaptcha?.ready(render)
    if (!script) { script = document.createElement('script'); script.src = 'https://www.google.com/recaptcha/api.js?render=explicit&hl=id'; script.async = true; script.dataset.simrisRecaptcha = 'true'; document.head.appendChild(script) }
    script.addEventListener('load', load)
    return () => script?.removeEventListener('load', load)
  }, [siteKey])

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setError('')
    setSending(true)
    try {
      await api.get('/sanctum/csrf-cookie')
      await api.post('/api/v1/lupa-kata-sandi', { email, captcha_token: token.current })
      setSent(true)
      toast.success('Jika email terdaftar, Firebase akan mengirim tautan reset password.')
    } catch (reason) { setError(errorMessage(reason)) }
    finally { setSending(false) }
  }

  return <div className={s.page}><section className={s.intro}><div className={s.brand}>SIMRIS</div><h1>Pulihkan akses akun.</h1><p>Firebase akan mengirim email untuk membuat kata sandi baru.</p></section><main className={s.formSide}><form className={s.form} onSubmit={submit}><h1>Lupa kata sandi</h1><p>Masukkan email yang terhubung ke akun Firebase Anda.</p><label htmlFor="forgot-email">Email</label><input id="forgot-email" type="email" required value={email} onChange={(event) => setEmail(event.target.value)} disabled={sending || sent}/><div ref={element}/>{siteKey && !captcha && <small>Selesaikan verifikasi reCAPTCHA.</small>}{error && <p role="alert" className="field-error">{error}</p>}{sent && <p role="status">Periksa kotak masuk dan folder spam untuk tautan reset Firebase.</p>}<button className="primary" disabled={!captcha || !siteKey || sending || sent}>{sending ? 'Mengirim…' : sent ? 'Email reset dikirim' : 'Kirim tautan reset'}</button><p className={s.switchMode}><Link to="/masuk">Kembali ke masuk</Link></p></form></main></div>
}
