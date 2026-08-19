import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { companyApi } from '@/api/companyApi'
import { useToast } from '@/hooks/useToast'
import PageHeader from '@/components/ui/PageHeader'
import Card from '@/components/ui/Card'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Textarea from '@/components/ui/Textarea'
import Modal from '@/components/ui/Modal'
import Spinner from '@/components/ui/Spinner'
import Toast from '@/components/ui/Toast'

interface Company { id: number; name: string; address?: string; phone?: string; email?: string; npwp?: string }
interface FormState { name: string; address: string; phone: string; email: string; npwp: string }
const empty: FormState = { name: '', address: '', phone: '', email: '', npwp: '' }

export default function CompanyPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()
  const [modal, setModal] = useState<{ open: boolean; editing: Company | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState>>({})

  const { data, isLoading } = useQuery({ queryKey: ['companies'], queryFn: () => companyApi.list().then(r => r.data.data as Company[]) })

  const saveMutation = useMutation({
    mutationFn: (payload: FormState) => modal.editing ? companyApi.update(modal.editing.id, payload) : companyApi.create(payload),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['companies'] }); closeModal(); showToast('Perusahaan berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => setForm(f => ({ ...f, [k]: e.target.value }))
  const openAdd = () => { setForm(empty); setErrors({}); setModal({ open: true, editing: null }) }
  const openEdit = (c: Company) => { setForm({ name: c.name, address: c.address ?? '', phone: c.phone ?? '', email: c.email ?? '', npwp: c.npwp ?? '' }); setErrors({}); setModal({ open: true, editing: c }) }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => { const e: Partial<FormState> = {}; if (!form.name.trim()) e.name = 'Nama wajib diisi.'; setErrors(e); return Object.keys(e).length === 0 }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  return (
    <div>
      <PageHeader title="Perusahaan" subtitle="Data perusahaan pengelola." onAdd={openAdd} />
      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).map(c => (
            <Card key={c.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
              <div>
                <div style={{ fontWeight: 600, color: '#111827' }}>{c.name}</div>
                <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>{[c.email, c.phone].filter(Boolean).join(' · ')}</div>
                {c.npwp && <div style={{ fontSize: '0.8125rem', color: '#9ca3af' }}>NPWP: {c.npwp}</div>}
              </div>
              <Button variant="secondary" size="sm" onClick={() => openEdit(c)}>Ubah</Button>
            </Card>
          ))}
          {(data ?? []).length === 0 && <Card><div style={{ color: '#9ca3af', textAlign: 'center', padding: '1.5rem' }}>Belum ada perusahaan.</div></Card>}
        </div>
      )}
      <Modal open={modal.open} title={modal.editing ? 'Ubah Perusahaan' : 'Tambah Perusahaan'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button type="submit" onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Input id="name" label="Nama Perusahaan *" value={form.name} onChange={set('name')} error={errors.name} />
          <Textarea id="address" label="Alamat" value={form.address} onChange={set('address')} />
          <Input id="phone" label="Telepon" value={form.phone} onChange={set('phone')} />
          <Input id="email" label="Email" type="email" value={form.email} onChange={set('email')} error={errors.email} />
          <Input id="npwp" label="NPWP" value={form.npwp} onChange={set('npwp')} />
        </div>
      </Modal>
      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
