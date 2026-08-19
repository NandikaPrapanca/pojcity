import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { projectApi } from '@/api/projectApi'
import { companyApi } from '@/api/companyApi'
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
import Badge from '@/components/ui/Badge'
import SearchBar from '@/components/ui/SearchBar'
import EmptyState from '@/components/ui/EmptyState'

interface Project { id: number; name: string; project_type: string; address?: string; notes?: string; company_id: number }
interface Company { id: number; name: string }

interface FormState { name: string; project_type: string; company_id: string; address: string; notes: string }
const empty: FormState = { name: '', project_type: '', company_id: '', address: '', notes: '' }

const typeOptions = [
  { value: 'residential', label: 'Residensial' },
  { value: 'commercial', label: 'Komersial' },
]
const typeLabel: Record<string, string> = { residential: 'Residensial', commercial: 'Komersial' }
const typeBadge = (t: string): 'green' | 'blue' => t === 'residential' ? 'green' : 'blue'

export default function ProjectPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  const [search, setSearch] = useState('')
  const [filterType, setFilterType] = useState('')
  const [modal, setModal] = useState<{ open: boolean; editing: Project | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState>>({})
  const [deleteTarget, setDeleteTarget] = useState<Project | null>(null)

  const params: Record<string, unknown> = {}
  if (search) params.search = search
  if (filterType) params.project_type = filterType

  const { data, isLoading } = useQuery({
    queryKey: ['projects', params],
    queryFn: () => projectApi.list(params).then(r => r.data.data as Project[]),
  })

  const { data: companies } = useQuery({
    queryKey: ['companies'],
    queryFn: () => companyApi.list().then(r => r.data.data as Company[]),
  })

  const companyOptions = (companies ?? []).map(c => ({ value: c.id, label: c.name }))

  const saveMutation = useMutation({
    mutationFn: (payload: FormState) => {
      const body = { ...payload, company_id: Number(payload.company_id) }
      return modal.editing ? projectApi.update(modal.editing.id, body) : projectApi.create(body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['projects'] }); closeModal(); showToast('Proyek berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => projectApi.delete(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['projects'] }); setDeleteTarget(null); showToast('Proyek berhasil dihapus.') },
    onError: (e: any) => { setDeleteTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))

  const openAdd = () => {
    const defaultCompany = companies?.[0]?.id?.toString() ?? ''
    setForm({ ...empty, company_id: defaultCompany })
    setErrors({})
    setModal({ open: true, editing: null })
  }
  const openEdit = (p: Project) => {
    setForm({ name: p.name, project_type: p.project_type, company_id: String(p.company_id), address: p.address ?? '', notes: p.notes ?? '' })
    setErrors({}); setModal({ open: true, editing: p })
  }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => {
    const e: Partial<FormState> = {}
    if (!form.name.trim()) e.name = 'Nama wajib diisi.'
    if (!form.project_type) e.project_type = 'Tipe wajib dipilih.'
    setErrors(e); return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  return (
    <div>
      <PageHeader title="Proyek" subtitle="Kelola data proyek." onAdd={openAdd} />

      <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
        <SearchBar value={search} onChange={setSearch} placeholder="Cari nama proyek..." />
        <select value={filterType} onChange={e => setFilterType(e.target.value)}
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: filterType ? '#111827' : '#9ca3af' }}>
          <option value="">Semua Tipe</option>
          {typeOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada proyek." />}
          {(data ?? []).map(p => (
            <Card key={p.id} style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
              <div>
                <div style={{ fontWeight: 600, color: '#111827' }}>{p.name}</div>
                <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginTop: '0.25rem' }}>
                  <Badge label={typeLabel[p.project_type] ?? p.project_type} color={typeBadge(p.project_type)} />
                  {p.address && <span style={{ fontSize: '0.8125rem', color: '#6b7280' }}>{p.address}</span>}
                </div>
              </div>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <Button variant="secondary" size="sm" onClick={() => openEdit(p)}>Ubah</Button>
                <Button variant="danger" size="sm" onClick={() => setDeleteTarget(p)}>Hapus</Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal open={modal.open} title={modal.editing ? 'Ubah Proyek' : 'Tambah Proyek'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Input id="proj-name" label="Nama Proyek *" value={form.name} onChange={set('name')} error={errors.name} />
          <Select id="proj-type" label="Tipe Proyek *" value={form.project_type} onChange={set('project_type')} options={typeOptions} placeholder="Pilih tipe..." error={errors.project_type} />
          {companyOptions.length > 0 && (
            <Select id="proj-company" label="Perusahaan" value={form.company_id} onChange={set('company_id')} options={companyOptions} />
          )}
          <Input id="proj-addr" label="Alamat" value={form.address} onChange={set('address')} />
          <Textarea id="proj-notes" label="Catatan" value={form.notes} onChange={set('notes')} />
        </div>
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus proyek "${deleteTarget?.name}"? Tindakan ini tidak dapat dibatalkan.`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
