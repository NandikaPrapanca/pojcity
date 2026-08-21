import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useNavigate, Navigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'
import { useState } from 'react'

const loginSchema = z.object({
  email: z.string().min(1, 'Email wajib diisi').email('Format email tidak valid'),
  password: z.string().min(6, 'Password minimal 6 karakter'),
})

type LoginFormData = z.infer<typeof loginSchema>

export default function LoginPage() {
  const navigate = useNavigate()
  const login = useAuthStore((s) => s.login)
  const isLoading = useAuthStore((s) => s.isLoading)
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated)
  const token = useAuthStore((s) => s.token)
  const [serverError, setServerError] = useState<string | null>(null)

  const hasToken = Boolean(
    (token ?? localStorage.getItem('token')) &&
    (token ?? localStorage.getItem('token')) !== 'undefined' &&
    (token ?? localStorage.getItem('token')) !== 'null' &&
    (token ?? localStorage.getItem('token'))?.trim() !== ''
  )

  // If already authenticated with a valid token, redirect to /dashboard
  if (isAuthenticated && hasToken) {
    return <Navigate to="/dashboard" replace />
  }

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  })

  const onSubmit = async (data: LoginFormData) => {
    setServerError(null)
    try {
      await login(data.email, data.password)
      navigate('/dashboard', { replace: true })
    } catch (error: unknown) {
      const err = error as { response?: { data?: { message?: string } } }
      setServerError(
        err.response?.data?.message ?? 'Login gagal. Coba lagi.'
      )
    }
  }

  return (
    <div style={styles.container}>
      <div style={styles.card}>
        {/* Logo / Brand */}
        <div style={styles.brandArea}>
          <h1 style={styles.brand}>IPU Billing</h1>
          <p style={styles.subtitle}>Invoice & Billing Management System</p>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} style={styles.form} noValidate>
          {serverError && (
            <div style={styles.errorAlert} role="alert">
              {serverError}
            </div>
          )}

          <div style={styles.fieldGroup}>
            <label htmlFor="email" style={styles.label}>
              Email
            </label>
            <input
              id="email"
              type="email"
              autoComplete="email"
              style={{
                ...styles.input,
                ...(errors.email ? styles.inputError : {}),
              }}
              {...register('email')}
              aria-describedby={errors.email ? 'email-error' : undefined}
              aria-invalid={!!errors.email}
            />
            {errors.email && (
              <span id="email-error" style={styles.fieldError} role="alert">
                {errors.email.message}
              </span>
            )}
          </div>

          <div style={styles.fieldGroup}>
            <label htmlFor="password" style={styles.label}>
              Password
            </label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              style={{
                ...styles.input,
                ...(errors.password ? styles.inputError : {}),
              }}
              {...register('password')}
              aria-describedby={errors.password ? 'password-error' : undefined}
              aria-invalid={!!errors.password}
            />
            {errors.password && (
              <span id="password-error" style={styles.fieldError} role="alert">
                {errors.password.message}
              </span>
            )}
          </div>

          <button
            type="submit"
            disabled={isLoading}
            style={{
              ...styles.submitButton,
              ...(isLoading ? styles.submitButtonDisabled : {}),
            }}
            aria-busy={isLoading}
          >
            {isLoading ? 'Masuk...' : 'Masuk'}
          </button>
        </form>
      </div>
    </div>
  )
}

const styles: Record<string, React.CSSProperties> = {
  container: {
    minHeight: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#f5f5f0',
    padding: '1rem',
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: '8px',
    boxShadow: '0 2px 12px rgba(0, 0, 0, 0.08)',
    padding: '2.5rem',
    width: '100%',
    maxWidth: '400px',
  },
  brandArea: {
    textAlign: 'center',
    marginBottom: '2rem',
  },
  brand: {
    fontSize: '1.75rem',
    fontWeight: '700',
    color: '#2d5a3d',
    margin: '0 0 0.25rem',
    letterSpacing: '-0.02em',
  },
  subtitle: {
    fontSize: '0.875rem',
    color: '#6b7280',
    margin: 0,
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.25rem',
  },
  errorAlert: {
    backgroundColor: '#fef2f2',
    border: '1px solid #fecaca',
    color: '#b91c1c',
    borderRadius: '6px',
    padding: '0.75rem 1rem',
    fontSize: '0.875rem',
  },
  fieldGroup: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.375rem',
  },
  label: {
    fontSize: '0.875rem',
    fontWeight: '500',
    color: '#374151',
  },
  input: {
    width: '100%',
    padding: '0.625rem 0.75rem',
    fontSize: '0.9375rem',
    border: '1px solid #d1d5db',
    borderRadius: '6px',
    outline: 'none',
    color: '#111827',
    backgroundColor: '#ffffff',
    boxSizing: 'border-box',
    transition: 'border-color 0.15s',
  },
  inputError: {
    borderColor: '#ef4444',
  },
  fieldError: {
    fontSize: '0.8125rem',
    color: '#ef4444',
  },
  submitButton: {
    width: '100%',
    padding: '0.75rem',
    fontSize: '0.9375rem',
    fontWeight: '600',
    color: '#ffffff',
    backgroundColor: '#2d5a3d',
    border: 'none',
    borderRadius: '6px',
    cursor: 'pointer',
    marginTop: '0.5rem',
    transition: 'background-color 0.15s',
  },
  submitButtonDisabled: {
    backgroundColor: '#9ca3af',
    cursor: 'not-allowed',
  },
}
