import { useAuthStore } from '@/stores/authStore'

export default function DashboardPage() {
  const user = useAuthStore((s) => s.user)

  return (
    <div>
      <div style={styles.welcomeCard}>
        <h3 style={styles.welcomeTitle}>
          Selamat datang, {user?.name ?? 'Pengguna'} 👋
        </h3>
        <p style={styles.welcomeText}>
          Sistem IPU Billing &amp; Invoice Management — Phase 1
        </p>
        {user && (
          <div style={styles.roleBadge}>
            <span style={styles.roleLabel}>Role:</span>{' '}
            <span style={styles.roleValue}>{user.role}</span>
          </div>
        )}
      </div>

      <div style={styles.grid}>
        <div style={styles.card}>
          <div style={styles.cardLabel}>Modul Aktif</div>
          <div style={styles.cardValue}>Autentikasi</div>
          <div style={styles.cardNote}>Phase 1 — Backend selesai</div>
        </div>

        <div style={styles.card}>
          <div style={styles.cardLabel}>Status Database</div>
          <div style={styles.cardValue}>Terhubung</div>
          <div style={styles.cardNote}>MySQL · ipu_billing</div>
        </div>

        <div style={styles.card}>
          <div style={styles.cardLabel}>Tahap Berikutnya</div>
          <div style={styles.cardValue}>Phase 2</div>
          <div style={styles.cardNote}>Master data, Customer, Project</div>
        </div>
      </div>
    </div>
  )
}

const styles: Record<string, React.CSSProperties> = {
  welcomeCard: {
    backgroundColor: '#ffffff',
    borderRadius: '8px',
    border: '1px solid #e5e7eb',
    padding: '1.5rem',
    marginBottom: '1.5rem',
  },
  welcomeTitle: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#111827',
    margin: '0 0 0.25rem',
  },
  welcomeText: {
    fontSize: '0.9rem',
    color: '#6b7280',
    margin: '0 0 0.75rem',
  },
  roleBadge: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.375rem',
    padding: '0.25rem 0.75rem',
    backgroundColor: '#ecfdf5',
    borderRadius: '999px',
    fontSize: '0.8125rem',
  },
  roleLabel: {
    color: '#6b7280',
  },
  roleValue: {
    color: '#2d5a3d',
    fontWeight: '600',
    textTransform: 'capitalize',
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
    gap: '1rem',
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: '8px',
    border: '1px solid #e5e7eb',
    padding: '1.25rem',
  },
  cardLabel: {
    fontSize: '0.8125rem',
    color: '#9ca3af',
    fontWeight: '500',
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
    marginBottom: '0.375rem',
  },
  cardValue: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#111827',
    marginBottom: '0.25rem',
  },
  cardNote: {
    fontSize: '0.8125rem',
    color: '#6b7280',
  },
}
