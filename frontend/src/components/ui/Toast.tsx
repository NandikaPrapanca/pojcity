import { useEffect } from 'react'
type ToastType = 'success' | 'error' | 'info'
interface Props { message: string; type?: ToastType; onClose: () => void }
const colors: Record<ToastType, { bg: string; border: string; text: string }> = {
  success: { bg: '#f0fdf4', border: '#86efac', text: '#16a34a' },
  error:   { bg: '#fef2f2', border: '#fca5a5', text: '#dc2626' },
  info:    { bg: '#eff6ff', border: '#93c5fd', text: '#1d4ed8' },
}
export default function Toast({ message, type = 'success', onClose }: Props) {
  useEffect(() => { const t = setTimeout(onClose, 3500); return () => clearTimeout(t) }, [onClose])
  const c = colors[type]
  return (
    <div style={{ position: 'fixed', bottom: '1.5rem', right: '1.5rem', zIndex: 2000, backgroundColor: c.bg, border: `1px solid ${c.border}`, color: c.text, borderRadius: 8, padding: '0.875rem 1.25rem', fontSize: '0.9rem', fontWeight: 500, boxShadow: '0 4px 12px rgba(0,0,0,0.1)', display: 'flex', alignItems: 'center', gap: '0.75rem', maxWidth: 380 }} role="alert">
      <span style={{ flex: 1 }}>{message}</span>
      <button onClick={onClose} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'inherit', fontSize: '1rem', padding: 0, lineHeight: 1 }}>✕</button>
    </div>
  )
}
