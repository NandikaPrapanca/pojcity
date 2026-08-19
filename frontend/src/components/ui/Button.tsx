import React from 'react'
type Variant = 'primary' | 'secondary' | 'danger' | 'ghost'
interface Props {
  children: React.ReactNode
  onClick?: () => void
  type?: 'button' | 'submit' | 'reset'
  variant?: Variant
  disabled?: boolean
  size?: 'sm' | 'md'
  style?: React.CSSProperties
}
const base: React.CSSProperties = { fontFamily: 'inherit', cursor: 'pointer', borderRadius: 6, fontWeight: 500, border: 'none', transition: 'background-color 0.15s', display: 'inline-flex', alignItems: 'center', gap: '0.375rem' }
const variants: Record<Variant, React.CSSProperties> = {
  primary:   { backgroundColor: '#2d5a3d', color: '#fff' },
  secondary: { backgroundColor: '#f3f4f6', color: '#374151', border: '1px solid #e5e7eb' },
  danger:    { backgroundColor: '#dc2626', color: '#fff' },
  ghost:     { backgroundColor: 'transparent', color: '#374151' },
}
const sizes = { sm: { padding: '0.375rem 0.75rem', fontSize: '0.8125rem' }, md: { padding: '0.625rem 1rem', fontSize: '0.9375rem' } }
export default function Button({ children, onClick, type = 'button', variant = 'primary', disabled, size = 'md', style }: Props) {
  return (
    <button type={type} onClick={onClick} disabled={disabled} style={{ ...base, ...variants[variant], ...sizes[size], ...(disabled ? { opacity: 0.5, cursor: 'not-allowed' } : {}), ...style }}>
      {children}
    </button>
  )
}
