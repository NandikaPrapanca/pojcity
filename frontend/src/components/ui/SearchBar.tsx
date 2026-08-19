interface Props { value: string; onChange: (v: string) => void; placeholder?: string }
export default function SearchBar({ value, onChange, placeholder = 'Cari...' }: Props) {
  return (
    <input value={value} onChange={e => onChange(e.target.value)} placeholder={placeholder}
      style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, color: '#111827', backgroundColor: '#fff', width: 260 }} />
  )
}
