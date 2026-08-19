import React from 'react'
interface Props extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
}
export default function Input({ label, error, id, ...props }: Props) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.375rem' }}>
      {label && <label htmlFor={id} style={{ fontSize: '0.875rem', fontWeight: 500, color: '#374151' }}>{label}</label>}
      <input id={id} {...props} style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: `1px solid ${error ? '#dc2626' : '#d1d5db'}`, borderRadius: 6, color: '#111827', backgroundColor: '#fff', width: '100%', boxSizing: 'border-box', ...props.style }} />
      {error && <span style={{ fontSize: '0.8125rem', color: '#dc2626' }} role="alert">{error}</span>}
    </div>
  )
}
