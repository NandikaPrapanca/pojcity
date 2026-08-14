import { useAuthStore } from '@/stores/authStore'

interface HeaderProps {
  title?: string
}

export default function Header({ title = 'Dashboard' }: HeaderProps) {
  const user = useAuthStore((s) => s.user)

  return (
    <header style={styles.header} role="banner">
      <div style={styles.left}>
        <h2 style={styles.pageTitle}>{title}</h2>
      </div>
      <div style={styles.right}>
        {user && (
          <div style={styles.userChip}>
            <span style={styles.userAvatar} aria-hidden="true">
              {user.name.charAt(0).toUpperCase()}
            </span>
            <span style={styles.userName}>{user.name}</span>
          </div>
        )}
      </div>
    </header>
  )
}

const styles: Record<string, React.CSSProperties> = {
  header: {
    height: '56px',
    backgroundColor: '#ffffff',
    borderBottom: '1px solid #e5e7eb',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: '0 1.5rem',
    flexShrink: 0,
  },
  left: {
    display: 'flex',
    alignItems: 'center',
  },
  pageTitle: {
    fontSize: '1rem',
    fontWeight: '600',
    color: '#111827',
    margin: 0,
  },
  right: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  userChip: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
  },
  userAvatar: {
    width: '32px',
    height: '32px',
    borderRadius: '50%',
    backgroundColor: '#2d5a3d',
    color: '#ffffff',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: '0.875rem',
    fontWeight: '600',
  },
  userName: {
    fontSize: '0.875rem',
    color: '#374151',
    fontWeight: '500',
  },
}
