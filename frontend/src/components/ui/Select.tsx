import React from 'react'
interface Props extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label?: string
  error?: string
  options: { value: string | number; label: string }[]
  placeholder?: string
}
export default function Select({ label, error, id, options, placeholder, ...props }: Props) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.375rem' }}>
      {label && <label htmlFor={id} style={{ fontSize: '0.875rem', fontWeight: 500, color: '#374151' }}>{label}</label>}
      <select id={id} {...props} style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: `1px solid ${error ? '#dc2626' : '#d1d5db'}`, borderRadius: 6, color: props.value ? '#111827' : '#9ca3af', backgroundColor: '#fff', width: '100%', boxSizing: 'border-box', ...props.style }}>
        {placeholder && <option value="">{placeholder}</option>}
        {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
      {error && <span style={{ fontSize: '0.8125rem', color: '#dc2626' }} role="alert">{error}</span>}
    </div>
  )
}
