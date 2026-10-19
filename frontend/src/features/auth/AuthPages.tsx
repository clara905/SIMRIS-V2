import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Eye, EyeOff, LockKeyhole, ShieldCheck, UserPlus } from 'lucide-react'
import { login, register, sessionKey, normalizeSession } from '../../api/auth'
import { errorMessage, fieldErrors } from '../../api/client'
import s from './Auth.module.css'

/* ─── Login ─────────────────────────────────────────────────────────────── */

const loginSchema = z.object({
  login: z.string().min(1, 'Masukkan username atau email.'),
  password: z.string().min(1, 'Masukkan kata sandi.'),
  totp: z.string().optional(),
})

export function LoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const client = useQueryClient()
  const [error, setError] = useState('')
  const [showPassword, setShowPassword] = useState(false)

  const {
    register: reg,
    handleSubmit,
    setError: setFieldError,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof loginSchema>>({ resolver: zodResolver(loginSchema) })

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
              await login(values)
              await client.invalidateQueries({ queryKey: sessionKey })
              navigate(location.state?.from || '/', { replace: true })
            } catch (err) {
              setError(errorMessage(err))
              for (const [key, messages] of Object.entries(fieldErrors(err))) {
                if (['login', 'password'].includes(key)) {
                  setFieldError(key as 'login' | 'password', { message: messages[0] })
                }
              }
            }
          })}
          noValidate
        >
          <h1>Masuk ke SIMRIS</h1>
          <p>Gunakan username atau email dan kata sandi untuk membuka ruang kerja Anda.</p>

          <label htmlFor="login">Username atau Email</label>
          <input
            id="login"
            type="text"
            autoComplete="username"
            placeholder="Masukkan username atau email"
            {...reg('login')}
            aria-invalid={!!errors.login}
            aria-describedby="login-error"
            disabled={isSubmitting}
          />
          <span id="login-error" className="field-error" aria-live="polite">
            {errors.login?.message}
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
