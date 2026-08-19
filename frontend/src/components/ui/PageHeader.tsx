import Button from './Button'
interface Props { title: string; subtitle?: string; onAdd?: () => void; addLabel?: string }
export default function PageHeader({ title, subtitle, onAdd, addLabel = 'Tambah' }: Props) {
  return (
    <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: '1.25rem', flexWrap: 'wrap', gap: '0.75rem' }}>
      <div>
        <h1 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 700, color: '#111827' }}>{title}</h1>
        {subtitle && <p style={{ margin: '0.25rem 0 0', fontSize: '0.875rem', color: '#6b7280' }}>{subtitle}</p>}
      </div>
      {onAdd && <Button onClick={onAdd}>{addLabel}</Button>}
    </div>
  )
}
