import React, { useEffect, useState } from 'react'
import { Navigate, Outlet } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'

interface ProtectedRouteProps {
  children?: React.ReactNode
}

/**
 * Wraps protected routes. If the user is not authenticated, redirects to /login.
 * On hard refresh, verifies the stored token against /auth/me.
 */
export default function ProtectedRoute({ children }: ProtectedRouteProps) {
  const { isAuthenticated, token, fetchMe } = useAuthStore()
  const rawToken = token ?? localStorage.getItem('token')
  const hasToken = Boolean(
    rawToken && rawToken !== 'undefined' && rawToken !== 'null' && rawToken.trim() !== ''
  )

  // If already authenticated (e.g. freshly logged in), no need to wait/check.
  // Only enter checking state on hard refresh when token exists but store is not hydrated.
  const [checking, setChecking] = useState<boolean>(() => hasToken && !isAuthenticated)

  useEffect(() => {
    if (hasToken && !isAuthenticated) {
      fetchMe().finally(() => setChecking(false))
    } else {
      setChecking(false)
    }
  }, [hasToken, isAuthenticated, fetchMe])

  // If no token exists at all, immediately redirect to /login
  if (!hasToken) {
    return <Navigate to="/login" replace />
  }

  // If checking token validity against the server on initial hard refresh
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

  // If unauthenticated after checking
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return children ? <>{children}</> : <Outlet />
}

