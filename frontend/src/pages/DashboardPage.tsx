import { Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'

interface QuickLink {
  title: string
  desc: string
  to: string
  icon: string
}

const quickLinks: QuickLink[] = [
  { title: 'Item Tagihan', desc: 'Kelola item tagihan IPL, Air, Listrik & Lainnya', to: '/billing', icon: '📋' },
  { title: 'Kepemilikan', desc: 'Kelola data kavling & customer', to: '/ownership', icon: '🏠' },
  { title: 'Meteran Air', desc: 'Pencatatan angka meter & upload foto', to: '/meter', icon: '⏱' },
  { title: 'Tarif & Pengaturan', desc: 'Tarif IPL, tarif air bertingkat & pajak', to: '/master/ipl-rates', icon: '💰' },
]

export default function DashboardPage() {
  const user = useAuthStore((s) => s.user)

  return (
    <div style={styles.container}>
      {/* Welcome Hero Card */}
      <div style={styles.welcomeCard}>
        <div style={styles.welcomeHeader}>
          <div>
            <h1 style={styles.welcomeTitle}>
              Selamat datang, {user?.name ?? 'Developer'} 👋
            </h1>
            <p style={styles.welcomeText}>
              Sistem IPU Billing &amp; Invoice Management
            </p>
          </div>
          {user && (
            <div style={styles.roleBadge}>
              <span style={styles.roleLabel}>Role:</span>{' '}
              <span style={styles.roleValue}>{user.role}</span>
            </div>
          )}
        </div>
      </div>

      {/* Overview Metric Cards */}
      <div style={styles.grid}>
        <div style={styles.card}>
          <div style={styles.cardLabel}>Status Sistem</div>
          <div style={styles.cardValueHighlight}>Phase 4 — Meter Reading</div>
          <div style={styles.cardNote}>Pencatatan meter air &amp; verifikasi foto aktif</div>
        </div>

        <div style={styles.card}>
          <div style={styles.cardLabel}>Tahap Berikutnya</div>
          <div style={styles.cardValue}>Phase 5 — Billing Engine</div>
          <div style={styles.cardNote}>Perhitungan tagihan IPL &amp; pemakaian air</div>
        </div>

        <div style={styles.card}>
          <div style={styles.cardLabel}>Status Database</div>
          <div style={styles.cardValue}>
            <span style={styles.onlineDot} aria-hidden="true" />
            Terhubung
          </div>
          <div style={styles.cardNote}>MySQL · ipu_billing</div>
        </div>
      </div>

      {/* Quick Links Section */}
      <div style={styles.section}>
        <h2 style={styles.sectionTitle}>Akses Cepat Modul</h2>
        <div style={styles.quickGrid}>
          {quickLinks.map((item) => (
            <Link key={item.to} to={item.to} style={styles.quickCard}>
              <span style={styles.quickIcon} aria-hidden="true">{item.icon}</span>
              <div style={styles.quickBody}>
                <div style={styles.quickTitle}>{item.title}</div>
                <div style={styles.quickDesc}>{item.desc}</div>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </div>
  )
}

const styles: Record<string, React.CSSProperties> = {
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.5rem',
  },
  welcomeCard: {
    backgroundColor: '#ffffff',
    borderRadius: '8px',
    border: '1px solid #e5e7eb',
    padding: '1.5rem',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.05)',
  },
  welcomeHeader: {
    display: 'flex',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    flexWrap: 'wrap',
    gap: '1rem',
  },
  welcomeTitle: {
    fontSize: '1.25rem',
    fontWeight: '700',
    color: '#111827',
    margin: '0 0 0.25rem',
    letterSpacing: '-0.01em',
  },
  welcomeText: {
    fontSize: '0.9375rem',
    color: '#6b7280',
    margin: 0,
  },
  roleBadge: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.375rem',
    padding: '0.35rem 0.85rem',
    backgroundColor: '#ecfdf5',
    border: '1px solid #a7f3d0',
    borderRadius: '999px',
    fontSize: '0.8125rem',
  },
  roleLabel: {
    color: '#065f46',
    fontWeight: '500',
  },
  roleValue: {
    color: '#047857',
    fontWeight: '700',
    textTransform: 'capitalize',
  },
  grid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
    gap: '1rem',
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: '8px',
    border: '1px solid #e5e7eb',
    padding: '1.25rem',
    boxShadow: '0 1px 3px rgba(0, 0, 0, 0.04)',
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'space-between',
  },
  cardLabel: {
    fontSize: '0.75rem',
    color: '#9ca3af',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
    marginBottom: '0.5rem',
  },
  cardValue: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#111827',
    marginBottom: '0.375rem',
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  cardValueHighlight: {
    fontSize: '1.125rem',
    fontWeight: '600',
    color: '#2d5a3d',
    marginBottom: '0.375rem',
  },
  onlineDot: {
    width: '8px',
    height: '8px',
    borderRadius: '50%',
    backgroundColor: '#10b981',
    display: 'inline-block',
  },
  cardNote: {
    fontSize: '0.8125rem',
    color: '#6b7280',
  },
  section: {
    marginTop: '0.5rem',
  },
  sectionTitle: {
    fontSize: '1rem',
    fontWeight: '600',
    color: '#374151',
    margin: '0 0 0.875rem',
  },
  quickGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
    gap: '1rem',
  },
  quickCard: {
    backgroundColor: '#ffffff',
    borderRadius: '8px',
    border: '1px solid #e5e7eb',
    padding: '1.125rem',
    display: 'flex',
    alignItems: 'flex-start',
    gap: '0.875rem',
    textDecoration: 'none',
    transition: 'border-color 0.15s, box-shadow 0.15s',
    boxShadow: '0 1px 2px rgba(0, 0, 0, 0.03)',
  },
  quickIcon: {
    fontSize: '1.5rem',
    lineHeight: 1,
    padding: '0.5rem',
    backgroundColor: '#f3f4f6',
    borderRadius: '6px',
  },
  quickBody: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.125rem',
  },
  quickTitle: {
    fontSize: '0.9375rem',
    fontWeight: '600',
    color: '#111827',
  },
  quickDesc: {
    fontSize: '0.8125rem',
    color: '#6b7280',
    lineHeight: 1.35,
  },
}
