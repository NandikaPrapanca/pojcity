import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { hierarchyApi } from '@/api/hierarchyApi'
import { projectApi } from '@/api/projectApi'
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

interface Project { id: number; name: string; project_type: string }
interface Cluster { id: number; name: string; project_id: number; project?: { name: string } }

interface FormState { project_id: string; name: string }
const empty: FormState = { project_id: '', name: '' }

export default function ClusterPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  const [filterProjectId, setFilterProjectId] = useState('')
  const [modal, setModal] = useState<{ open: boolean; editing: Cluster | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState>>({})
  const [deleteTarget, setDeleteTarget] = useState<Cluster | null>(null)

  const { data: projects } = useQuery({
    queryKey: ['projects', { project_type: 'residential' }],
    queryFn: () => projectApi.list({ project_type: 'residential' }).then(r => r.data.data as Project[]),
  })

  const residentialProjects = (projects ?? []).filter(p => p.project_type === 'residential')
  const projectOptions = residentialProjects.map(p => ({ value: p.id, label: p.name }))

  const params: Record<string, unknown> = {}
  if (filterProjectId) params.project_id = filterProjectId

  const { data, isLoading } = useQuery({
    queryKey: ['clusters', params],
    queryFn: () => hierarchyApi.listClusters(params).then(r => r.data.data as Cluster[]),
  })

  const saveMutation = useMutation({
    mutationFn: (payload: FormState) => {
      const body = { ...payload, project_id: Number(payload.project_id) }
      return modal.editing ? hierarchyApi.updateCluster(modal.editing.id, body) : hierarchyApi.createCluster(body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['clusters'] }); closeModal(); showToast('Cluster berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => hierarchyApi.deleteCluster(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['clusters'] }); setDeleteTarget(null); showToast('Cluster berhasil dihapus.') },
    onError: (e: any) => { setDeleteTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))

  const openAdd = () => { setForm(empty); setErrors({}); setModal({ open: true, editing: null }) }
  const openEdit = (c: Cluster) => { setForm({ project_id: String(c.project_id), name: c.name }); setErrors({}); setModal({ open: true, editing: c }) }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => {
    const e: Partial<FormState> = {}
    if (!form.project_id) e.project_id = 'Proyek wajib dipilih.'
    if (!form.name.trim()) e.name = 'Nama wajib diisi.'
    setErrors(e); return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  const getProjectName = (projectId: number) => residentialProjects.find(p => p.id === projectId)?.name ?? '-'

  return (
    <div>
      <PageHeader title="Cluster" subtitle="Kelola data cluster perumahan." onAdd={residentialProjects.length > 0 ? openAdd : undefined} />

      {residentialProjects.length === 0 && (
        <Card style={{ marginBottom: '1rem', backgroundColor: '#fffbeb', borderColor: '#fcd34d' }}>
          <p style={{ margin: 0, color: '#92400e', fontSize: '0.875rem' }}>
            ℹ️ Belum ada proyek residensial. Tambahkan proyek bertipe Residensial terlebih dahulu.
          </p>
        </Card>
      )}

      <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
        <select value={filterProjectId} onChange={e => setFilterProjectId(e.target.value)}
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: filterProjectId ? '#111827' : '#9ca3af' }}>
          <option value="">Semua Proyek</option>
          {projectOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada cluster." />}
          {(data ?? []).map(c => (
            <Card key={c.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
              <div>
                <div style={{ fontWeight: 600, color: '#111827' }}>{c.name}</div>
                <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>Proyek: {c.project?.name ?? getProjectName(c.project_id)}</div>
              </div>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <Button variant="secondary" size="sm" onClick={() => openEdit(c)}>Ubah</Button>
                <Button variant="danger" size="sm" onClick={() => setDeleteTarget(c)}>Hapus</Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal open={modal.open} title={modal.editing ? 'Ubah Cluster' : 'Tambah Cluster'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Select id="cl-project" label="Proyek Residensial *" value={form.project_id} onChange={set('project_id')} options={projectOptions} placeholder="Pilih proyek..." error={errors.project_id} />
          <Input id="cl-name" label="Nama Cluster *" value={form.name} onChange={set('name')} error={errors.name} />
        </div>
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus cluster "${deleteTarget?.name}"? Tindakan ini tidak dapat dibatalkan.`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
