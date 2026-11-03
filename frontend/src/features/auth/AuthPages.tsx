import { toast } from 'sonner'
import { api } from '@/api/client'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Eye, EyeOff, LockKeyhole, ShieldCheck, UserPlus } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { loginWithFirebase, loginWithPassword, register, sessionKey, normalizeSession } from '../../api/auth'
import { errorMessage, fieldErrors } from '../../api/client'
import s from './Auth.module.css'

/* ─── Login ─────────────────────────────────────────────────────────────── */

const loginSchema = z.object({
  email: z.string().min(1, 'Masukkan username atau alamat email.'),
  password: z.string().min(1, 'Masukkan kata sandi.'),
  captcha_token: z.string().optional(),
  totp: z.string().optional(),
})

export function LoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const client = useQueryClient()
  const [error, setError] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const firebaseAuth = import.meta.env.VITE_FIREBASE_AUTH === 'true'
  const [captchaReady, setCaptchaReady] = useState(false)
  const [captchaSiteKey, setCaptchaSiteKey] = useState(import.meta.env.VITE_RECAPTCHA_SITE_KEY || '')
  const [captchaConfigError, setCaptchaConfigError] = useState('')
  const captchaElement = useRef<HTMLDivElement>(null)
  const captchaWidgetId = useRef<number | null>(null)
  const captchaToken = useRef('')
  const {
    register: reg,
    handleSubmit,
    setError: setFieldError,
    setValue,
    clearErrors,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof loginSchema>>({ resolver: zodResolver(loginSchema) })

  useEffect(() => {
    let dibatalkan = false
    const muatSiteKey = async () => {
      if (captchaSiteKey) return captchaSiteKey
      try {
        const { data } = await api.get<{ recaptcha_site_key?: string }>('/api/v1/config')
        if (!dibatalkan && data.recaptcha_site_key) {
          setCaptchaSiteKey(data.recaptcha_site_key)
          setCaptchaConfigError('')
        } else if (!dibatalkan) {
          setCaptchaConfigError('Site key captcha kosong di konfigurasi backend.')
        }
        return data.recaptcha_site_key || ''
      } catch {
        if (!dibatalkan) setCaptchaConfigError('Tidak bisa memuat konfigurasi reCAPTCHA dari backend. Pastikan Laravel aktif dan endpoint /api/v1/config bisa diakses.')
        return ''
      }
    }

    void muatSiteKey()
    return () => { dibatalkan = true }
  }, [captchaSiteKey])

  useEffect(() => {
    const siteKey = captchaSiteKey
    if (!siteKey || !captchaElement.current) return

    const renderCaptcha = () => {
      if (!window.grecaptcha || !captchaElement.current || captchaWidgetId.current !== null) return
      captchaWidgetId.current = window.grecaptcha.render(captchaElement.current, {
        sitekey: siteKey,
        callback: (token) => {
          captchaToken.current = token
          setValue('captcha_token', token, { shouldValidate: true })
          clearErrors('captcha_token')
        },
        'expired-callback': () => {
          captchaToken.current = ''
          setValue('captcha_token', '', { shouldValidate: true })
        },
        'error-callback': () => {
          captchaToken.current = ''
          setValue('captcha_token', '', { shouldValidate: true })
        },
      })
      setCaptchaReady(true)
    }

    if (window.grecaptcha) {
      window.grecaptcha.ready(renderCaptcha)
      return
    }

    let script = document.querySelector<HTMLScriptElement>('script[data-simris-recaptcha]')
    const handleLoad = () => window.grecaptcha?.ready(renderCaptcha)
    if (script) {
      script.addEventListener('load', handleLoad)
      return () => script?.removeEventListener('load', handleLoad)
    }

    script = document.createElement('script')
    script.src = 'https://www.google.com/recaptcha/api.js?render=explicit&hl=id'
    script.async = true
    script.defer = true
    script.dataset.simrisRecaptcha = 'true'
    script.addEventListener('load', handleLoad)
    document.head.appendChild(script)

    return () => script?.removeEventListener('load', handleLoad)
  }, [captchaSiteKey, clearErrors, setValue])

  return (
    <div className={s.page}>
      <section className={s.intro}>
        <div className={s.brand}>SIMRIS</div>
        <h1>
          Tertib mengelola.
          <br />
          Terukur menghadapi risiko.
        </h1>
        <p>Satu ruang kerja untuk menjaga data, metode, dan tata kelola risiko instansi.</p>
        <div className={s.register}>
          <span>Data yang terjaga</span>
          <span>Keputusan yang terlacak</span>
        </div>
      </section>
      <main className={s.formSide}>
        <form
          className={s.form}
          onSubmit={handleSubmit(async (values) => {
            setError('')
            try {
              if (firebaseAuth) {
                await loginWithFirebase({
                  email: values.email,
                  password: values.password,
                  captcha_token: captchaToken.current,
                })
              } else {
                await loginWithPassword({
                  login: values.email,
                  password: values.password,
                  totp: values.totp,
                })
              }
              await client.invalidateQueries({ queryKey: sessionKey })
              navigate(location.state?.from || '/', { replace: true })
            } catch (err) {
              setError(errorMessage(err))
              if (firebaseAuth) {
                captchaToken.current = ''
                setValue('captcha_token', '', { shouldValidate: false })
                if (captchaWidgetId.current !== null) window.grecaptcha?.reset(captchaWidgetId.current)
              }
              for (const [key, messages] of Object.entries(fieldErrors(err))) {
                if (['email', 'password', 'captcha_token'].includes(key)) {
                  setFieldError(key as 'email' | 'password' | 'captcha_token', { message: messages[0] })
                }
              }
            }
          })}
          noValidate
        >
          <h1>Masuk ke SIMRIS</h1>
          <p>Gunakan email dan kata sandi akun SIMRIS Anda.</p>

          <label htmlFor="email">{firebaseAuth ? 'Email' : 'Username atau Email'}</label>
          <input
            id="email"
            type={firebaseAuth ? 'email' : 'text'}
            autoComplete="username"
            placeholder={firebaseAuth ? 'nama@instansi.go.id' : 'Masukkan username atau email'}
            {...reg('email')}
            aria-invalid={!!errors.email}
            aria-describedby="email-error"
            disabled={isSubmitting}
          />
          <span id="email-error" className="field-error" aria-live="polite">
            {errors.email?.message}
          </span>

          <label htmlFor="password">Kata sandi</label>
          <div className={s.passwordWrapper}>
            <input
              id="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              placeholder="Masukkan kata sandi"
              {...reg('password')}
              aria-invalid={!!errors.password}
              aria-describedby="password-error"
              disabled={isSubmitting}
            />
            <button
              type="button"
              className={s.togglePassword}
              onClick={() => setShowPassword((prev) => !prev)}
              aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
              tabIndex={-1}
            >
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          </div>
          <span id="password-error" className="field-error" aria-live="polite">
            {errors.password?.message}
          </span>

          {firebaseAuth && captchaSiteKey ? (
            <div className={s.captchaContainer}>
              <div ref={captchaElement} />
              {!captchaReady && <span>Memuat verifikasi captcha…</span>}
              <span className="field-error" aria-live="polite">{errors.captcha_token?.message}</span>
            </div>
          ) : firebaseAuth ? (
            <p role="alert" className="field-error">
              {captchaConfigError || 'reCAPTCHA belum dikonfigurasi. Isi VITE_RECAPTCHA_SITE_KEY di backend.'}
            </p>
          ) : null}

          {error && (
            <p role="alert" className="field-error">
              {error}
            </p>
          )}

          <button className="primary" type="submit" disabled={isSubmitting}>
            <LockKeyhole size={16} />
            {isSubmitting ? 'Memeriksa akun…' : 'Masuk ke ruang kerja'}
          </button>

          <p className={s.switchMode}>
            Belum punya akun?{' '}
            <Link to="/daftar">Daftar sekarang</Link>
          </p>

          {import.meta.env.VITE_API_MOCK === 'true' && (
            <div className={s.demo}>
              <strong>Akun demonstrasi</strong>
              <br />
              Username: admin
              <br />
              Kata sandi: Simris123!
              <br />
              Data di lingkungan ini merupakan simulasi.
            </div>
          )}
        </form>
      </main>
    </div>
  )
}

/* ─── Register ───────────────────────────────────────────────────────────── */

const registerSchema = z
  .object({
    username: z.string().min(3, 'Username minimal 3 karakter.').max(255),
    email: z.string().min(1, 'Masukkan email.').email('Format email tidak valid.'),
    password: z.string().min(8, 'Kata sandi minimal 8 karakter.'),
    password_confirmation: z.string().min(1, 'Konfirmasi kata sandi wajib diisi.'),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Konfirmasi harus sama dengan kata sandi.',
  })

export function RegisterPage() {
  const navigate = useNavigate()
  const client = useQueryClient()
  const [error, setError] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirm, setShowConfirm] = useState(false)

  const {
    register: reg,
    handleSubmit,
    setError: setFieldError,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof registerSchema>>({ resolver: zodResolver(registerSchema) })

  return (
    <div className={s.page}>
      <section className={s.intro}>
        <div className={s.brand}>SIMRIS</div>
        <h1>
          Mulai kelola risiko.
          <br />
          Bersama tim Anda.
        </h1>
        <p>Buat akun untuk mengakses ruang kerja manajemen risiko instansi Anda.</p>
        <div className={s.register}>
          <span>Daftar cepat</span>
          <span>Lengkapi profil setelah masuk</span>
        </div>
      </section>
      <main className={s.formSide}>
        <form
          className={s.form}
          onSubmit={handleSubmit(async (values) => {
            setError('')
            try {
              const data = await register(values)
              if (data?.data) {
                // session was set by the backend – normalise and hydrate cache
                client.setQueryData(sessionKey, normalizeSession(data.data))
              } else {
                await client.invalidateQueries({ queryKey: sessionKey })
              }
              navigate('/', { replace: true })
            } catch (err) {
              setError(errorMessage(err))
              for (const [key, messages] of Object.entries(fieldErrors(err))) {
                if (['username', 'email', 'password', 'password_confirmation'].includes(key)) {
                  setFieldError(
                    key as 'username' | 'email' | 'password' | 'password_confirmation',
                    { message: messages[0] },
                  )
                }
              }
            }
          })}
          noValidate
        >
          <h1>Buat akun SIMRIS</h1>
          <p>Isi data berikut untuk mendaftarkan akun Anda.</p>

          <label htmlFor="reg-username">Username</label>
          <input
            id="reg-username"
            type="text"
            autoComplete="username"
            placeholder="Pilih username unik Anda"
            {...reg('username')}
            aria-invalid={!!errors.username}
            aria-describedby="reg-username-error"
            disabled={isSubmitting}
          />
          <span id="reg-username-error" className="field-error" aria-live="polite">
            {errors.username?.message}
          </span>

          <label htmlFor="reg-email">Email</label>
          <input
            id="reg-email"
            type="email"
            autoComplete="email"
            placeholder="nama@instansi.go.id"
            {...reg('email')}
            aria-invalid={!!errors.email}
            aria-describedby="reg-email-error"
            disabled={isSubmitting}
          />
          <span id="reg-email-error" className="field-error" aria-live="polite">
            {errors.email?.message}
          </span>

          <label htmlFor="reg-password">Kata sandi</label>
          <div className={s.passwordWrapper}>
            <input
              id="reg-password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="new-password"
              placeholder="Minimal 8 karakter"
              {...reg('password')}
              aria-invalid={!!errors.password}
              aria-describedby="reg-password-error"
              disabled={isSubmitting}
            />
            <button
              type="button"
              className={s.togglePassword}
              onClick={() => setShowPassword((p) => !p)}
              aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
              tabIndex={-1}
            >
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          </div>
          <span id="reg-password-error" className="field-error" aria-live="polite">
            {errors.password?.message}
          </span>

          <label htmlFor="reg-password-confirmation">Konfirmasi kata sandi</label>
          <div className={s.passwordWrapper}>
            <input
              id="reg-password-confirmation"
              type={showConfirm ? 'text' : 'password'}
              autoComplete="new-password"
              placeholder="Ulangi kata sandi"
              {...reg('password_confirmation')}
              aria-invalid={!!errors.password_confirmation}
              aria-describedby="reg-confirm-error"
              disabled={isSubmitting}
            />
            <button
              type="button"
              className={s.togglePassword}
              onClick={() => setShowConfirm((p) => !p)}
              aria-label={showConfirm ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
              tabIndex={-1}
            >
              {showConfirm ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          </div>
          <span id="reg-confirm-error" className="field-error" aria-live="polite">
            {errors.password_confirmation?.message}
          </span>

          {error && (
            <p role="alert" className="field-error">
              {error}
            </p>
          )}

          <button className="primary" type="submit" disabled={isSubmitting}>
            <UserPlus size={16} />
            {isSubmitting ? 'Mendaftarkan akun…' : 'Buat akun'}
          </button>

          <p className={s.switchMode}>
            Sudah punya akun?{' '}
            <Link to="/masuk">Masuk di sini</Link>
          </p>
        </form>
      </main>
    </div>
  )
}


const passwordSchema = z
  .object({
    current_password:
      import.meta.env.VITE_API_MOCK === 'true'
        ? z.string().optional()
        : z.string().min(1, 'Masukkan kata sandi saat ini.'),
    password: z.string().min(12, 'Gunakan sedikitnya 12 karakter.'),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Konfirmasi harus sama dengan kata sandi baru.',
  })

export function PasswordPage() {
  const navigate = useNavigate()
  const client = useQueryClient()
  const [error, setError] = useState('')
  const {
    register,
    handleSubmit,
    setError: setFieldError,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof passwordSchema>>({ resolver: zodResolver(passwordSchema) })
  return (
    <main className="session-loading">
      <form
        onSubmit={handleSubmit(async (values) => {
          try {
            await api.post(
              import.meta.env.VITE_API_MOCK === 'true' ? '/api/password' : '/api/ganti-password',
              values,
            )
            await client.invalidateQueries({ queryKey: sessionKey })
            toast.success('Kata sandi diperbarui')
            navigate('/')
          } catch (err) {
            setError(errorMessage(err))
            for (const [key, messages] of Object.entries(fieldErrors(err)))
              if (
                key === 'password' ||
                key === 'password_confirmation' ||
                key === 'current_password'
              )
                setFieldError(key, { message: messages[0] })
          }
        })}
      >
        <h1>Ganti kata sandi</h1>
        <p>
          Anda perlu mengganti kata sandi awal sebelum menggunakan SIMRIS. Gunakan sedikitnya 12
          karakter.
        </p>
        {import.meta.env.VITE_API_MOCK !== 'true' && (
          <div>
            <label htmlFor="current_password">Kata sandi saat ini</label>
            <input
              id="current_password"
              type="password"
              autoComplete="current-password"
              {...register('current_password')}
            />
            <span className="field-error">{errors.current_password?.message}</span>
          </div>
        )}
        {(['password', 'password_confirmation'] as const).map((key, index) => (
          <div key={key}>
            <label htmlFor={key}>{index ? 'Konfirmasi kata sandi baru' : 'Kata sandi baru'}</label>
            <input
              id={key}
              type="password"
              autoComplete="new-password"
              {...register(key)}
              aria-invalid={!!errors[key]}
              aria-describedby={`${key}-error`}
            />
            <span id={`${key}-error`} className="field-error" aria-live="polite">
              {errors[key]?.message}
            </span>
          </div>
        ))}
        {error && <p role="alert">{error}</p>}
        <button className="primary" disabled={isSubmitting}>
          {isSubmitting ? 'Menyimpan…' : 'Simpan kata sandi'}
        </button>
      </form>
    </main>
  )
}

export function ForbiddenPage() {
  const location = useLocation()
  return (
    <main className="session-loading">
      <ShieldCheck size={36} />
      <h1>Anda tidak memiliki akses</h1>
      <p>
        {location.state?.reason ||
          'Role scope Anda tidak mengizinkan halaman ini. Hubungi pengelola akun untuk memeriksa akses Anda.'}
      </p>
      <Link to="/masuk">Kembali ke halaman masuk</Link>
    </main>
  )
}
