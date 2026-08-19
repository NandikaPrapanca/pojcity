import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { customerApi } from '@/api/customerApi'
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

interface Pic { id: number; name: string; phone?: string; email?: string; position?: string }
interface Customer {
  id: number
  name: string
  customer_type: string
  nik?: string
  npwp?: string
  whatsapp?: string
  email?: string
  billing_address?: string
  notes?: string
}

interface FormState {
  name: string
  customer_type: string
  nik: string
  npwp: string
  whatsapp: string
  email: string
  billing_address: string
  notes: string
}
const empty: FormState = { name: '', customer_type: '', nik: '', npwp: '', whatsapp: '', email: '', billing_address: '', notes: '' }

const typeOptions = [
  { value: 'individual', label: 'Perorangan' },
  { value: 'cv', label: 'CV' },
  { value: 'pt', label: 'PT' },
  { value: 'institution', label: 'Institusi' },
]
const typeLabel: Record<string, string> = { individual: 'Perorangan', cv: 'CV', pt: 'PT', institution: 'Institusi' }
const typeBadgeColor = (t: string): 'gray' | 'blue' | 'green' => {
  if (t === 'individual') return 'gray'
  if (t === 'pt') return 'green'
  return 'blue'
}

interface PicFormState { name: string; phone: string; email: string; position: string }
const emptyPic: PicFormState = { name: '', phone: '', email: '', position: '' }

export default function CustomerPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  const [search, setSearch] = useState('')
  const [filterType, setFilterType] = useState('')
  const [expandedId, setExpandedId] = useState<number | null>(null)

  const [modal, setModal] = useState<{ open: boolean; editing: Customer | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState>>({})
  const [deleteTarget, setDeleteTarget] = useState<Customer | null>(null)

  // PIC state
  const [picModal, setPicModal] = useState<{ open: boolean; customerId: number | null; editing: Pic | null }>({ open: false, customerId: null, editing: null })
  const [picForm, setPicForm] = useState<PicFormState>(emptyPic)
  const [picErrors, setPicErrors] = useState<Partial<PicFormState>>({})
  const [deletePicTarget, setDeletePicTarget] = useState<{ customerId: number; pic: Pic } | null>(null)

  const params: Record<string, unknown> = {}
  if (search) params.search = search
  if (filterType) params.customer_type = filterType

  const { data, isLoading } = useQuery({
    queryKey: ['customers', params],
    queryFn: () => customerApi.list(params).then(r => r.data.data as Customer[]),
  })

  const { data: pics } = useQuery({
    queryKey: ['pics', expandedId],
    queryFn: () => expandedId ? customerApi.listPics(expandedId).then(r => r.data.data as Pic[]) : Promise.resolve([]),
    enabled: expandedId !== null,
  })

  const saveMutation = useMutation({
    mutationFn: (payload: FormState) => modal.editing ? customerApi.update(modal.editing.id, payload) : customerApi.create(payload),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['customers'] }); closeModal(); showToast('Customer berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => customerApi.delete(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['customers'] }); setDeleteTarget(null); showToast('Customer berhasil dihapus.') },
    onError: (e: any) => { setDeleteTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const savePicMutation = useMutation({
    mutationFn: (payload: PicFormState) => {
      if (!picModal.customerId) throw new Error('No customer')
      return picModal.editing
        ? customerApi.updatePic(picModal.customerId, picModal.editing.id, payload)
        : customerApi.createPic(picModal.customerId, payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['pics', picModal.customerId] })
      closePicModal()
      showToast('PIC berhasil disimpan.')
    },
    onError: (e: any) => { setPicErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const deletePicMutation = useMutation({
    mutationFn: ({ customerId, picId }: { customerId: number; picId: number }) => customerApi.deletePic(customerId, picId),
    onSuccess: () => {
      if (deletePicTarget) qc.invalidateQueries({ queryKey: ['pics', deletePicTarget.customerId] })
      setDeletePicTarget(null)
      showToast('PIC berhasil dihapus.')
    },
    onError: (e: any) => { setDeletePicTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))
  const setPic = (k: keyof PicFormState) => (e: React.ChangeEvent<HTMLInputElement>) => setPicForm(f => ({ ...f, [k]: e.target.value }))

  const openAdd = () => { setForm(empty); setErrors({}); setModal({ open: true, editing: null }) }
  const openEdit = (c: Customer) => {
    setForm({ name: c.name, customer_type: c.customer_type, nik: c.nik ?? '', npwp: c.npwp ?? '', whatsapp: c.whatsapp ?? '', email: c.email ?? '', billing_address: c.billing_address ?? '', notes: c.notes ?? '' })
    setErrors({}); setModal({ open: true, editing: c })
  }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => {
    const e: Partial<FormState> = {}
    if (!form.name.trim()) e.name = 'Nama wajib diisi.'
    if (!form.customer_type) e.customer_type = 'Tipe wajib dipilih.'
    setErrors(e); return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  const openAddPic = (customerId: number) => { setPicForm(emptyPic); setPicErrors({}); setPicModal({ open: true, customerId, editing: null }) }
  const openEditPic = (customerId: number, pic: Pic) => { setPicForm({ name: pic.name, phone: pic.phone ?? '', email: pic.email ?? '', position: pic.position ?? '' }); setPicErrors({}); setPicModal({ open: true, customerId, editing: pic }) }
  const closePicModal = () => setPicModal({ open: false, customerId: null, editing: null })
  const validatePic = () => { const e: Partial<PicFormState> = {}; if (!picForm.name.trim()) e.name = 'Nama wajib diisi.'; setPicErrors(e); return Object.keys(e).length === 0 }
  const handlePicSubmit = () => { if (validatePic()) savePicMutation.mutate(picForm) }

  const toggleExpand = (id: number) => setExpandedId(p => p === id ? null : id)

  return (
    <div>
      <PageHeader title="Customer" subtitle="Kelola data pelanggan." onAdd={openAdd} />

      {/* Filters */}
      <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
        <SearchBar value={search} onChange={setSearch} placeholder="Cari nama customer..." />
        <select value={filterType} onChange={e => setFilterType(e.target.value)}
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: filterType ? '#111827' : '#9ca3af' }}>
          <option value="">Semua Tipe</option>
          {typeOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada customer." />}
          {(data ?? []).map(c => (
            <Card key={c.id}>
              <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '0.5rem', flexWrap: 'wrap' }}>
                <div style={{ flex: 1 }}>
                  <button onClick={() => toggleExpand(c.id)} style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 0, textAlign: 'left' }}>
                    <span style={{ fontWeight: 600, color: '#111827', fontSize: '0.9375rem' }}>{c.name}</span>
                  </button>
                  <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center', marginTop: '0.25rem', flexWrap: 'wrap' }}>
                    <Badge label={typeLabel[c.customer_type] ?? c.customer_type} color={typeBadgeColor(c.customer_type)} />
                    {c.whatsapp && <span style={{ fontSize: '0.8125rem', color: '#6b7280' }}>WA: {c.whatsapp}</span>}
                    {c.email && <span style={{ fontSize: '0.8125rem', color: '#6b7280' }}>{c.email}</span>}
                  </div>
                </div>
                <div style={{ display: 'flex', gap: '0.5rem' }}>
                  <Button variant="secondary" size="sm" onClick={() => toggleExpand(c.id)}>{expandedId === c.id ? 'Tutup PIC' : 'PIC'}</Button>
                  <Button variant="secondary" size="sm" onClick={() => openEdit(c)}>Ubah</Button>
                  <Button variant="danger" size="sm" onClick={() => setDeleteTarget(c)}>Hapus</Button>
                </div>
              </div>

              {/* PIC panel */}
              {expandedId === c.id && (
                <div style={{ marginTop: '1rem', borderTop: '1px solid #e5e7eb', paddingTop: '1rem' }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '0.75rem' }}>
                    <span style={{ fontWeight: 600, fontSize: '0.875rem', color: '#374151' }}>Daftar PIC</span>
                    <Button size="sm" onClick={() => openAddPic(c.id)}>Tambah PIC</Button>
                  </div>
                  {(pics ?? []).length === 0 && <div style={{ color: '#9ca3af', fontSize: '0.875rem' }}>Belum ada PIC.</div>}
                  {(pics ?? []).map(p => (
                    <div key={p.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0.5rem 0', borderBottom: '1px solid #f3f4f6' }}>
                      <div>
                        <div style={{ fontWeight: 500, fontSize: '0.875rem', color: '#111827' }}>{p.name}</div>
                        <div style={{ fontSize: '0.8125rem', color: '#6b7280' }}>{[p.position, p.phone, p.email].filter(Boolean).join(' · ')}</div>
                      </div>
                      <div style={{ display: 'flex', gap: '0.5rem' }}>
                        <Button variant="secondary" size="sm" onClick={() => openEditPic(c.id, p)}>Ubah</Button>
                        <Button variant="danger" size="sm" onClick={() => setDeletePicTarget({ customerId: c.id, pic: p })}>Hapus</Button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </Card>
          ))}
        </div>
      )}

      {/* Customer modal */}
      <Modal open={modal.open} title={modal.editing ? 'Ubah Customer' : 'Tambah Customer'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Input id="cust-name" label="Nama *" value={form.name} onChange={set('name')} error={errors.name} />
          <Select id="cust-type" label="Tipe Customer *" value={form.customer_type} onChange={set('customer_type')} options={typeOptions} placeholder="Pilih tipe..." error={errors.customer_type} />
          <Input id="cust-nik" label="NIK" value={form.nik} onChange={set('nik')} />
          <Input id="cust-npwp" label="NPWP" value={form.npwp} onChange={set('npwp')} />
          <Input id="cust-wa" label="WhatsApp" value={form.whatsapp} onChange={set('whatsapp')} />
          <Input id="cust-email" label="Email" type="email" value={form.email} onChange={set('email')} />
          <Textarea id="cust-addr" label="Alamat Tagihan" value={form.billing_address} onChange={set('billing_address')} />
          <Textarea id="cust-notes" label="Catatan" value={form.notes} onChange={set('notes')} />
        </div>
      </Modal>

      {/* PIC modal */}
      <Modal open={picModal.open} title={picModal.editing ? 'Ubah PIC' : 'Tambah PIC'} onClose={closePicModal}
        footer={<><Button variant="secondary" onClick={closePicModal}>Batal</Button><Button onClick={handlePicSubmit} disabled={savePicMutation.isPending}>{savePicMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Input id="pic-name" label="Nama *" value={picForm.name} onChange={setPic('name')} error={picErrors.name} />
          <Input id="pic-pos" label="Jabatan" value={picForm.position} onChange={setPic('position')} />
          <Input id="pic-phone" label="Telepon" value={picForm.phone} onChange={setPic('phone')} />
          <Input id="pic-email" label="Email" type="email" value={picForm.email} onChange={setPic('email')} />
        </div>
      </Modal>

      {/* Delete customer confirm */}
      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus customer "${deleteTarget?.name}"? Tindakan ini tidak dapat dibatalkan.`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {/* Delete PIC confirm */}
      <ConfirmDialog
        open={!!deletePicTarget}
        message={`Hapus PIC "${deletePicTarget?.pic.name}"?`}
        onConfirm={() => deletePicTarget && deletePicMutation.mutate({ customerId: deletePicTarget.customerId, picId: deletePicTarget.pic.id })}
        onCancel={() => setDeletePicTarget(null)}
        loading={deletePicMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
