import { useEffect, useState } from 'react'
import { Navigate, Outlet } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'

/**
 * Wraps protected routes. If the user is not authenticated, redirects to /login.
 * On first load, verifies the stored token against /auth/me.
 */
export default function ProtectedRoute() {
  const { isAuthenticated, token, fetchMe } = useAuthStore()
  const [checking, setChecking] = useState(true)

  useEffect(() => {
    // If we have a stored token, verify it's still valid
    if (token) {
      fetchMe().finally(() => setChecking(false))
    } else {
      setChecking(false)
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  if (checking) {
    return (
      <div
        style={{
          minHeight: '100vh',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: '#f5f5f0',
          color: '#6b7280',
          fontSize: '0.9375rem',
        }}
        aria-live="polite"
        aria-label="Memuat..."
      >
        Memuat...
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return <Outlet />
}
