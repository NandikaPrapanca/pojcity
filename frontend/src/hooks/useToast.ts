import { useState, useCallback } from 'react'
type ToastType = 'success' | 'error' | 'info'
interface ToastState { message: string; type: ToastType; id: number }
export function useToast() {
  const [toast, setToast] = useState<ToastState | null>(null)
  const showToast = useCallback((message: string, type: ToastType = 'success') => {
    setToast({ message, type, id: Date.now() })
  }, [])
  const hideToast = useCallback(() => setToast(null), [])
  return { toast, showToast, hideToast }
}
