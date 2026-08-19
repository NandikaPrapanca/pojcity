import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { hierarchyApi } from '@/api/hierarchyApi'
import { useToast } from '@/hooks/useToast'
import PageHeader from '@/components/ui/PageHeader'
import Card from '@/components/ui/Card'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Textarea from '@/components/ui/Textarea'
import Select from '@/components/ui/Select'
import Modal from '@/components/ui/Modal'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import Spinner from '@/components/ui/Spinner'
import Toast from '@/components/ui/Toast'
import EmptyState from '@/components/ui/EmptyState'

interface Block { id: number; name: string; cluster?: { name: string; project?: { name: string } } }
interface Lot { id: number; lot_number: string; area?: number; notes?: string; block_id: number; block?: { name: string; cluster?: { name: string; project?: { name: string } } } }

interface FormState { block_id: string; lot_number: string; area: string; notes: string }
const empty: FormState = { block_id: '', lot_number: '', area: '', notes: '' }

export default function LotPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  const [filterBlockId, setFilterBlockId] = useState('')
  const [modal, setModal] = useState<{ open: boolean; editing: Lot | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState>>({})
  const [deleteTarget, setDeleteTarget] = useState<Lot | null>(null)

  const { data: blocks } = useQuery({
    queryKey: ['blocks', {}],
    queryFn: () => hierarchyApi.listBlocks().then(r => r.data.data as Block[]),
  })

  const blockOptions = (blocks ?? []).map(b => ({
    value: b.id,
    label: `Blok ${b.name}${b.cluster?.name ? ` · ${b.cluster.name}` : ''}${b.cluster?.project?.name ? ` · ${b.cluster.project.name}` : ''}`,
  }))

  const params: Record<string, unknown> = {}
  if (filterBlockId) params.block_id = filterBlockId

  const { data, isLoading } = useQuery({
    queryKey: ['lots', params],
    queryFn: () => hierarchyApi.listLots(params).then(r => r.data.data as Lot[]),
  })

  const saveMutation = useMutation({
    mutationFn: (payload: FormState) => {
      const body: Record<string, unknown> = {
        block_id: Number(payload.block_id),
        lot_number: payload.lot_number,
        notes: payload.notes,
      }
      if (payload.area) body.area = parseFloat(payload.area)
      return modal.editing ? hierarchyApi.updateLot(modal.editing.id, body) : hierarchyApi.createLot(body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['lots'] }); closeModal(); showToast('Kavling berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => hierarchyApi.deleteLot(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['lots'] }); setDeleteTarget(null); showToast('Kavling berhasil dihapus.') },
    onError: (e: any) => { setDeleteTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))

  const openAdd = () => { setForm(empty); setErrors({}); setModal({ open: true, editing: null }) }
  const openEdit = (l: Lot) => {
    setForm({ block_id: String(l.block_id), lot_number: l.lot_number, area: l.area != null ? String(l.area) : '', notes: l.notes ?? '' })
    setErrors({}); setModal({ open: true, editing: l })
  }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => {
    const e: Partial<FormState> = {}
    if (!form.block_id) e.block_id = 'Blok wajib dipilih.'
    if (!form.lot_number.trim()) e.lot_number = 'Nomor kavling wajib diisi.'
    setErrors(e); return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  return (
    <div>
      <PageHeader title="Kavling" subtitle="Kelola data kavling/lot." onAdd={openAdd} />

      <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
        <select value={filterBlockId} onChange={e => setFilterBlockId(e.target.value)}
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: filterBlockId ? '#111827' : '#9ca3af', minWidth: 200 }}>
          <option value="">Semua Blok</option>
          {blockOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada kavling." />}
          {(data ?? []).map(l => (
            <Card key={l.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
              <div>
                <div style={{ fontWeight: 600, color: '#111827' }}>Kavling {l.lot_number}</div>
                <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>
                  {l.area != null && <span>Luas: {l.area} m² · </span>}
                  {l.block?.name && `Blok ${l.block.name}`}
                  {l.block?.cluster?.name && ` · ${l.block.cluster.name}`}
                  {l.block?.cluster?.project?.name && ` · ${l.block.cluster.project.name}`}
                </div>
              </div>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <Button variant="secondary" size="sm" onClick={() => openEdit(l)}>Ubah</Button>
                <Button variant="danger" size="sm" onClick={() => setDeleteTarget(l)}>Hapus</Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal open={modal.open} title={modal.editing ? 'Ubah Kavling' : 'Tambah Kavling'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Select id="lot-block" label="Blok *" value={form.block_id} onChange={set('block_id')} options={blockOptions} placeholder="Pilih blok..." error={errors.block_id} />
          <Input id="lot-num" label="Nomor Kavling *" value={form.lot_number} onChange={set('lot_number')} error={errors.lot_number} />
          <Input id="lot-area" label="Luas (m²)" type="number" step="0.01" min="0" value={form.area} onChange={set('area')} />
          <Textarea id="lot-notes" label="Catatan" value={form.notes} onChange={set('notes')} />
        </div>
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus kavling "${deleteTarget?.lot_number}"? Tindakan ini tidak dapat dibatalkan.`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
