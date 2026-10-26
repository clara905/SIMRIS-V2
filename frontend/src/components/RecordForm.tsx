import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { errorMessage, fieldErrors } from '../api/client'
import s from './RecordForm.module.css'
export interface FormField {
  name: string
  label: string
  type?: 'text' | 'email' | 'date' | 'textarea' | 'select' | 'color' | 'number'
  options?: { value: string; label: string }[]
  required?: boolean
  hint?: string
  pattern?: RegExp
}
export function RecordForm({
  fields,
  values = {},
  onSave,
  onCancel,
  submitLabel = 'Simpan perubahan',
}: {
  fields: FormField[]
  values?: Record<string, string>
  onSave: (values: Record<string, string>) => Promise<void>
  onCancel?: () => void
  submitLabel?: string
}) {
  const schema = z.object(
    Object.fromEntries(
      fields.map((field) => {
        let validator = z.string().trim()
        if (field.required !== false) validator = validator.min(1, `${field.label} wajib diisi.`)
        if (field.type === 'email') validator = validator.email('Masukkan alamat email yang valid.')
        if (field.pattern)
          validator = validator.regex(
            field.pattern,
            `${field.label} tidak valid. ${field.hint || ''}`,
          )
        return [field.name, validator]
      }),
    ),
  )
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<Record<string, string>>({ defaultValues: values, resolver: zodResolver(schema) })
  const [error, setMessage] = useState('')
  return (
    <form
      noValidate
      onSubmit={handleSubmit(async (data) => {
        setMessage('')
        try {
          await onSave(data)
        } catch (err) {
          setMessage(errorMessage(err))
          Object.entries(fieldErrors(err)).forEach(([key, messages]) =>
            setError(key, { message: messages[0] }),
          )
        }
      })}
    >
      {fields.map((field) => (
        <div key={field.name}>
          <label htmlFor={`field-${field.name}`}>
            {field.label}
            {field.required === false && <span className={s.optional}> (opsional)</span>}
          </label>
          {field.type === 'select' ? (
            <select
              id={`field-${field.name}`}
              {...register(field.name)}
              aria-invalid={!!errors[field.name]}
              aria-describedby={`hint-${field.name}`}
            >
              <option value="">Pilih {field.label.toLowerCase()}</option>
              {field.options?.map((option) => (
                <option value={option.value} key={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          ) : field.type === 'textarea' ? (
            <textarea
              id={`field-${field.name}`}
              rows={3}
              {...register(field.name)}
              aria-describedby={`hint-${field.name}`}
              aria-invalid={!!errors[field.name]}
            />
          ) : (
            <input
              id={`field-${field.name}`}
              type={field.type || 'text'}
              {...register(field.name)}
              aria-invalid={!!errors[field.name]}
              aria-describedby={`hint-${field.name}`}
            />
          )}
          <div
            id={`hint-${field.name}`}
            aria-live="polite"
            className={errors[field.name] ? 'field-error' : s.hint}
          >
            {errors[field.name]?.message || field.hint}
          </div>
        </div>
      ))}
      {error && (
        <p role="alert" className="field-error">
          {error}
        </p>
      )}
      <div className={s.actions}>
        {onCancel && (
          <button type="button" onClick={onCancel} disabled={isSubmitting}>
            Batal
          </button>
        )}
        <button type="submit" className="primary" disabled={isSubmitting}>
          {isSubmitting ? 'Menyimpan…' : submitLabel}
        </button>
      </div>
    </form>
  )
}
