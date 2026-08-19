interface Props { label: string; color?: 'green' | 'gray' | 'blue' | 'red' }
const colors = {
  green: { bg: '#dcfce7', text: '#16a34a' },
  gray:  { bg: '#f3f4f6', text: '#6b7280' },
  blue:  { bg: '#dbeafe', text: '#1d4ed8' },
  red:   { bg: '#fee2e2', text: '#dc2626' },
}
export default function Badge({ label, color = 'gray' }: Props) {
  const c = colors[color]
  return <span style={{ display: 'inline-block', padding: '0.125rem 0.625rem', borderRadius: 999, fontSize: '0.75rem', fontWeight: 600, backgroundColor: c.bg, color: c.text }}>{label}</span>
}
