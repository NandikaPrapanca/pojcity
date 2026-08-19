import Modal from './Modal'
import Button from './Button'
interface Props { open: boolean; title?: string; message: string; onConfirm: () => void; onCancel: () => void; loading?: boolean }
export default function ConfirmDialog({ open, title = 'Konfirmasi', message, onConfirm, onCancel, loading }: Props) {
  return (
    <Modal open={open} title={title} onClose={onCancel} width={400}
      footer={<><Button variant="secondary" onClick={onCancel} disabled={loading}>Batal</Button><Button variant="danger" onClick={onConfirm} disabled={loading}>{loading ? 'Menghapus...' : 'Hapus'}</Button></>}>
      <p style={{ margin: 0, color: '#374151' }}>{message}</p>
    </Modal>
  )
}
