import { NavLink, useNavigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/authStore'

interface NavItem {
  label: string
  to: string
  icon: string
}

const navItems: NavItem[] = [
  { label: 'Dashboard',         to: '/dashboard',           icon: '◉' },
  { label: 'Kepemilikan',       to: '/ownership',           icon: '🏠' },
  { label: 'Meteran Air',       to: '/meter',               icon: '⏱' },
  { label: 'Perusahaan',        to: '/master/company',      icon: '🏢' },
  { label: 'Customer',          to: '/master/customers',    icon: '👥' },
  { label: 'Proyek',            to: '/master/projects',     icon: '🏗' },
  { label: 'Cluster',           to: '/master/clusters',     icon: '🗂' },
  { label: 'Blok',              to: '/master/blocks',       icon: '📦' },
  { label: 'Kavling',           to: '/master/lots',         icon: '📐' },
  { label: 'Tarif IPL',         to: '/master/ipl-rates',    icon: '💰' },
  { label: 'Tarif Air',         to: '/master/water-rates',  icon: '💧' },
  { label: 'Pajak',             to: '/master/tax',          icon: '📊' },
  { label: 'Tanda Tangan',      to: '/master/signatures',   icon: '✍' },
]

export default function Sidebar() {
  const logout = useAuthStore((s) => s.logout)
  const user = useAuthStore((s) => s.user)
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <aside style={styles.sidebar} aria-label="Navigasi utama">
      {/* Brand */}
      <div style={styles.brand}>
        <span style={styles.brandName}>IPU Billing</span>
        <span style={styles.brandSub}>Management System</span>
      </div>

      {/* Navigation */}
      <nav style={styles.nav}>
        {navItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            style={({ isActive }) => ({
              ...styles.navLink,
              ...(isActive ? styles.navLinkActive : {}),
            })}
          >
            <span style={styles.navIcon} aria-hidden="true">
              {item.icon}
            </span>
            {item.label}
          </NavLink>
        ))}
      </nav>

      {/* Footer / User info */}
      <div style={styles.sidebarFooter}>
        {user && (
          <div style={styles.userInfo}>
            <div style={styles.userName}>{user.name}</div>
            <div style={styles.userRole}>{user.role}</div>
          </div>
        )}
        <button
          onClick={handleLogout}
          style={styles.logoutButton}
          aria-label="Keluar dari aplikasi"
        >
          Keluar
        </button>
      </div>
    </aside>
  )
}

const styles: Record<string, React.CSSProperties> = {
  sidebar: {
    width: '220px',
    minHeight: '100vh',
    backgroundColor: '#2d5a3d',
    display: 'flex',
    flexDirection: 'column',
    flexShrink: 0,
  },
  brand: {
    padding: '1.5rem 1.25rem 1rem',
    borderBottom: '1px solid rgba(255,255,255,0.1)',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.125rem',
  },
  brandName: {
    color: '#ffffff',
    fontSize: '1.125rem',
    fontWeight: '700',
    letterSpacing: '-0.01em',
  },
  brandSub: {
    color: 'rgba(255,255,255,0.6)',
    fontSize: '0.75rem',
  },
  nav: {
    flex: 1,
    padding: '0.75rem 0.75rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.25rem',
  },
  navLink: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.625rem',
    padding: '0.625rem 0.75rem',
    borderRadius: '6px',
    color: 'rgba(255,255,255,0.75)',
    textDecoration: 'none',
    fontSize: '0.9rem',
    fontWeight: '500',
    transition: 'background-color 0.15s, color 0.15s',
  },
  navLinkActive: {
    backgroundColor: 'rgba(255,255,255,0.12)',
    color: '#ffffff',
  },
  navIcon: {
    fontSize: '0.875rem',
    opacity: 0.8,
  },
  sidebarFooter: {
    padding: '1rem 1.25rem',
    borderTop: '1px solid rgba(255,255,255,0.1)',
  },
  userInfo: {
    marginBottom: '0.75rem',
  },
  userName: {
    color: '#ffffff',
    fontSize: '0.875rem',
    fontWeight: '600',
  },
  userRole: {
    color: 'rgba(255,255,255,0.55)',
    fontSize: '0.75rem',
    textTransform: 'capitalize',
  },
  logoutButton: {
    width: '100%',
    padding: '0.5rem 0.75rem',
    fontSize: '0.875rem',
    color: 'rgba(255,255,255,0.8)',
    backgroundColor: 'transparent',
    border: '1px solid rgba(255,255,255,0.2)',
    borderRadius: '6px',
    cursor: 'pointer',
    textAlign: 'left',
    transition: 'background-color 0.15s',
  },
}
