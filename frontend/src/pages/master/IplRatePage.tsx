import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { pricingApi } from '@/api/pricingApi'
import { projectApi } from '@/api/projectApi'
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

interface Project { id: number; name: string }
interface IplRate {
  id: number
  name: string
  rate_per_sqm: number
  effective_date: string
  notes?: string
  project_id: number
  project?: { name: string }
}

interface FormState { project_id: string; name: string; rate_per_sqm: string; effective_date: string; notes: string }
const empty: FormState = { project_id: '', name: '', rate_per_sqm: '', effective_date: '', notes: '' }

const formatRupiah = (amount: number) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)

const formatDate = (dateStr: string) => {
  try { return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }
  catch { return dateStr }
}

export default function IplRatePage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  const [filterProjectId, setFilterProjectId] = useState('')
  const [modal, setModal] = useState<{ open: boolean; editing: IplRate | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState>>({})
  const [deleteTarget, setDeleteTarget] = useState<IplRate | null>(null)

  const { data: projects } = useQuery({
    queryKey: ['projects', {}],
    queryFn: () => projectApi.list().then(r => r.data.data as Project[]),
  })
  const projectOptions = (projects ?? []).map(p => ({ value: p.id, label: p.name }))

  const params: Record<string, unknown> = {}
  if (filterProjectId) params.project_id = filterProjectId

  const { data, isLoading } = useQuery({
    queryKey: ['ipl-rates', params],
    queryFn: () => pricingApi.listIplRates(params).then(r => r.data.data as IplRate[]),
  })

  const saveMutation = useMutation({
    mutationFn: (payload: FormState) => {
      const body = { project_id: Number(payload.project_id), name: payload.name, rate_per_sqm: parseFloat(payload.rate_per_sqm), effective_date: payload.effective_date, notes: payload.notes }
      return modal.editing ? pricingApi.updateIplRate(modal.editing.id, body) : pricingApi.createIplRate(body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ipl-rates'] }); closeModal(); showToast('Tarif IPL berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => pricingApi.deleteIplRate(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['ipl-rates'] }); setDeleteTarget(null); showToast('Tarif IPL berhasil dihapus.') },
    onError: (e: any) => { setDeleteTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))

  const openAdd = () => { setForm(empty); setErrors({}); setModal({ open: true, editing: null }) }
  const openEdit = (r: IplRate) => {
    setForm({ project_id: String(r.project_id), name: r.name, rate_per_sqm: String(r.rate_per_sqm), effective_date: r.effective_date?.substring(0, 10) ?? '', notes: r.notes ?? '' })
    setErrors({}); setModal({ open: true, editing: r })
  }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => {
    const e: Partial<FormState> = {}
    if (!form.project_id) e.project_id = 'Proyek wajib dipilih.'
    if (!form.name.trim()) e.name = 'Nama wajib diisi.'
    if (!form.rate_per_sqm || isNaN(parseFloat(form.rate_per_sqm))) e.rate_per_sqm = 'Tarif wajib diisi.'
    if (!form.effective_date) e.effective_date = 'Tanggal berlaku wajib diisi.'
    setErrors(e); return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  return (
    <div>
      <PageHeader title="Tarif IPL" subtitle="Kelola tarif IPL per proyek." onAdd={openAdd} />

      <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
        <select value={filterProjectId} onChange={e => setFilterProjectId(e.target.value)}
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: filterProjectId ? '#111827' : '#9ca3af' }}>
          <option value="">Semua Proyek</option>
          {projectOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada tarif IPL." />}
          {(data ?? []).map(r => (
            <Card key={r.id} style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
              <div>
                <div style={{ fontWeight: 600, color: '#111827' }}>{r.name}</div>
                <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>
                  {formatRupiah(r.rate_per_sqm)}/m² · Berlaku: {formatDate(r.effective_date)}
                </div>
                {r.project?.name && <div style={{ fontSize: '0.8125rem', color: '#9ca3af' }}>Proyek: {r.project.name}</div>}
              </div>
              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <Button variant="secondary" size="sm" onClick={() => openEdit(r)}>Ubah</Button>
                <Button variant="danger" size="sm" onClick={() => setDeleteTarget(r)}>Hapus</Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal open={modal.open} title={modal.editing ? 'Ubah Tarif IPL' : 'Tambah Tarif IPL'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Select id="ipl-project" label="Proyek *" value={form.project_id} onChange={set('project_id')} options={projectOptions} placeholder="Pilih proyek..." error={errors.project_id} />
          <Input id="ipl-name" label="Nama Tarif *" value={form.name} onChange={set('name')} placeholder="Contoh: Standard, Khusus 50%" error={errors.name} />
          <Input id="ipl-rate" label="Tarif per m² (Rp) *" type="number" min="0" step="0.01" value={form.rate_per_sqm} onChange={set('rate_per_sqm')} error={errors.rate_per_sqm} />
          <Input id="ipl-date" label="Tanggal Berlaku *" type="date" value={form.effective_date} onChange={set('effective_date')} error={errors.effective_date} />
          <Textarea id="ipl-notes" label="Catatan" value={form.notes} onChange={set('notes')} />
        </div>
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus tarif IPL "${deleteTarget?.name}"? Tindakan ini tidak dapat dibatalkan.`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
