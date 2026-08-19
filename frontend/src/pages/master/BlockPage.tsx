import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { hierarchyApi } from '@/api/hierarchyApi'
import { useToast } from '@/hooks/useToast'
import PageHeader from '@/components/ui/PageHeader'
import Card from '@/components/ui/Card'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Select from '@/components/ui/Select'
import Modal from '@/components/ui/Modal'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import Spinner from '@/components/ui/Spinner'
import Toast from '@/components/ui/Toast'
import EmptyState from '@/components/ui/EmptyState'

interface Cluster { id: number; name: string; project?: { name: string } }
interface Block { id: number; name: string; cluster_id: number; cluster?: { name: string; project?: { name: string } } }

interface FormState { cluster_id: string; name: string }
const empty: FormState = { cluster_id: '', name: '' }

export default function BlockPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  const [filterClusterId, setFilterClusterId] = useState('')
  const [modal, setModal] = useState<{ open: boolean; editing: Block | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState>>({})
  const [deleteTarget, setDeleteTarget] = useState<Block | null>(null)

  const { data: clusters } = useQuery({
    queryKey: ['clusters', {}],
    queryFn: () => hierarchyApi.listClusters().then(r => r.data.data as Cluster[]),
  })

  const clusterOptions = (clusters ?? []).map(c => ({ value: c.id, label: `${c.name}${c.project?.name ? ` (${c.project.name})` : ''}` }))

  const params: Record<string, unknown> = {}
  if (filterClusterId) params.cluster_id = filterClusterId

  const { data, isLoading } = useQuery({
    queryKey: ['blocks', params],
    queryFn: () => hierarchyApi.listBlocks(params).then(r => r.data.data as Block[]),
  })

  const saveMutation = useMutation({
    mutationFn: (payload: FormState) => {
      const body = { ...payload, cluster_id: Number(payload.cluster_id) }
      return modal.editing ? hierarchyApi.updateBlock(modal.editing.id, body) : hierarchyApi.createBlock(body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['blocks'] }); closeModal(); showToast('Blok berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => hierarchyApi.deleteBlock(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['blocks'] }); setDeleteTarget(null); showToast('Blok berhasil dihapus.') },
    onError: (e: any) => { setDeleteTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))

  const openAdd = () => { setForm(empty); setErrors({}); setModal({ open: true, editing: null }) }
  const openEdit = (b: Block) => { setForm({ cluster_id: String(b.cluster_id), name: b.name }); setErrors({}); setModal({ open: true, editing: b }) }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => {
    const e: Partial<FormState> = {}
    if (!form.cluster_id) e.cluster_id = 'Cluster wajib dipilih.'
    if (!form.name.trim()) e.name = 'Nama wajib diisi.'
    setErrors(e); return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  return (
    <div>
      <PageHeader title="Blok" subtitle="Kelola data blok." onAdd={openAdd} />

      <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
        <select value={filterClusterId} onChange={e => setFilterClusterId(e.target.value)}
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: filterClusterId ? '#111827' : '#9ca3af' }}>
          <option value="">Semua Cluster</option>
          {clusterOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada blok." />}
          {(data ?? []).map(b => (
            <Card key={b.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
              <div>
                <div style={{ fontWeight: 600, color: '#111827' }}>Blok {b.name}</div>
                <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>
                  {b.cluster?.name && `Cluster: ${b.cluster.name}`}
                  {b.cluster?.project?.name && ` · Proyek: ${b.cluster.project.name}`}
                </div>
              </div>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <Button variant="secondary" size="sm" onClick={() => openEdit(b)}>Ubah</Button>
                <Button variant="danger" size="sm" onClick={() => setDeleteTarget(b)}>Hapus</Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal open={modal.open} title={modal.editing ? 'Ubah Blok' : 'Tambah Blok'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Select id="blk-cluster" label="Cluster *" value={form.cluster_id} onChange={set('cluster_id')} options={clusterOptions} placeholder="Pilih cluster..." error={errors.cluster_id} />
          <Input id="blk-name" label="Nama Blok *" value={form.name} onChange={set('name')} error={errors.name} />
        </div>
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus blok "${deleteTarget?.name}"? Tindakan ini tidak dapat dibatalkan.`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
