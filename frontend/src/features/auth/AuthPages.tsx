import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { ShieldCheck, LockKeyhole } from 'lucide-react'
import { toast } from 'sonner'
import { login, sessionKey } from '../../api/auth'
import { api, errorMessage, fieldErrors } from '../../api/client'
import s from './Auth.module.css'

const loginSchema = z.object({
  email: z.email('Masukkan alamat email yang valid.'),
  password: z.string().min(1, 'Masukkan kata sandi.'),
})
export function LoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const client = useQueryClient()
  const [error, setError] = useState('')
  const {
    register,
    handleSubmit,
    setError: setFieldError,
    formState: { errors, isSubmitting },
  } = useForm<z.infer<typeof loginSchema>>({ resolver: zodResolver(loginSchema) })
  return (
    <div className={s.page}>
      <section className={s.intro}>
        <div className={s.brand}>
          <ShieldCheck size={32} />
          SIMRIS
        </div>
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
              for (const [key, messages] of Object.entries(fieldErrors(err)))
                if (key === 'email' || key === 'password')
                  setFieldError(key, { message: messages[0] })
            }
          })}
          noValidate
        >
          <h1>Masuk ke SIMRIS</h1>
          <p>Gunakan akun instansi untuk membuka ruang kerja Anda.</p>
          <label htmlFor="email">Email</label>
          <input
            id="email"
            type="email"
            autoComplete="username"
            {...register('email')}
            aria-invalid={!!errors.email}
            aria-describedby="email-error"
          />
          <span id="email-error" className="field-error" aria-live="polite">
            {errors.email?.message}
          </span>
          <label htmlFor="password">Kata sandi</label>
          <input
            id="password"
            type="password"
            autoComplete="current-password"
            {...register('password')}
            aria-invalid={!!errors.password}
            aria-describedby="password-error"
          />
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
          {import.meta.env.VITE_API_MOCK === 'true' && (
            <div className={s.demo}>
              <strong>Akun demonstrasi</strong>
              <br />
              admin@simris.go.id
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
const passwordSchema = z
  .object({
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
            await api.post('/api/password', values)
            await client.invalidateQueries({ queryKey: sessionKey })
            toast.success('Kata sandi diperbarui')
            navigate('/')
          } catch (err) {
            setError(errorMessage(err))
            for (const [key, messages] of Object.entries(fieldErrors(err)))
              if (key === 'password' || key === 'password_confirmation')
                setFieldError(key, { message: messages[0] })
          }
        })}
      >
        <h1>Ganti kata sandi</h1>
        <p>
          Anda perlu mengganti kata sandi awal sebelum menggunakan SIMRIS. Gunakan sedikitnya 12
          karakter.
        </p>
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
