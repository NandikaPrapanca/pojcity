interface Props { message?: string }
export default function EmptyState({ message = 'Belum ada data.' }: Props) {
  return <div style={{ textAlign: 'center', padding: '3rem 1rem', color: '#9ca3af', fontSize: '0.9375rem' }}>{message}</div>
}
