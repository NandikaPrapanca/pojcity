import React, { useState, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { signatureApi } from '@/api/signatureApi'
import { companyApi } from '@/api/companyApi'
import { useToast } from '@/hooks/useToast'
import PageHeader from '@/components/ui/PageHeader'
import Card from '@/components/ui/Card'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Modal from '@/components/ui/Modal'
import ConfirmDialog from '@/components/ui/ConfirmDialog'
import Spinner from '@/components/ui/Spinner'
import Toast from '@/components/ui/Toast'
import EmptyState from '@/components/ui/EmptyState'
import Badge from '@/components/ui/Badge'

interface Company { id: number; name: string }
interface Signature {
  id: number
  company_id: number
  label: string
  name: string
  position?: string
  signature_path?: string
  is_active: number
}

interface FormState {
  label: string
  name: string
  position: string
  is_active: string
}
const empty: FormState = { label: '', name: '', position: '', is_active: '1' }

const API_BASE = import.meta.env.VITE_API_BASE_URL?.replace('/api/v1', '') ?? 'http://localhost:8080'

export default function SignaturePage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()
  const fileRef = useRef<HTMLInputElement>(null)

  const [modal, setModal] = useState<{ open: boolean; editing: Signature | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState & { signature_image: string }>>({})
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<Signature | null>(null)

  const { data: companies } = useQuery({
    queryKey: ['companies'],
    queryFn: () => companyApi.list().then(r => r.data.data as Company[]),
  })
  const defaultCompanyId = companies?.[0]?.id ?? 1

  const { data, isLoading } = useQuery({
    queryKey: ['signatures'],
    queryFn: () => signatureApi.list().then(r => r.data.data as Signature[]),
  })

  const saveMutation = useMutation({
    mutationFn: () => {
      const fd = new FormData()
      fd.append('company_id', String(defaultCompanyId))
      fd.append('label', form.label)
      fd.append('name', form.name)
      fd.append('position', form.position)
      fd.append('is_active', form.is_active)
      if (selectedFile) fd.append('signature_image', selectedFile)
      return modal.editing
        ? signatureApi.update(modal.editing.id, fd)
        : signatureApi.create(fd)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['signatures'] })
      closeModal()
      showToast('Tanda tangan berhasil disimpan.')
    },
    onError: (e: any) => {
      setErrors(e.response?.data?.errors ?? {})
      showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => signatureApi.delete(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['signatures'] }); setDeleteTarget(null); showToast('Tanda tangan berhasil dihapus.') },
    onError: (e: any) => { setDeleteTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null
    if (!file) { setSelectedFile(null); setPreviewUrl(null); return }

    // Client-side validation
    const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
    if (!allowed.includes(file.type)) {
      setErrors(prev => ({ ...prev, signature_image: 'Format tidak valid. Gunakan JPG, PNG, GIF, atau WebP.' }))
      setSelectedFile(null)
      setPreviewUrl(null)
      return
    }
    if (file.size > 2 * 1024 * 1024) {
      setErrors(prev => ({ ...prev, signature_image: 'Ukuran file maks 2 MB.' }))
      setSelectedFile(null)
      setPreviewUrl(null)
      return
    }
    setErrors(prev => ({ ...prev, signature_image: undefined }))
    setSelectedFile(file)
    setPreviewUrl(URL.createObjectURL(file))
  }

  const openAdd = () => {
    setForm(empty)
    setErrors({})
    setSelectedFile(null)
    setPreviewUrl(null)
    setModal({ open: true, editing: null })
  }

  const openEdit = (s: Signature) => {
    setForm({ label: s.label, name: s.name, position: s.position ?? '', is_active: String(s.is_active) })
    setErrors({})
    setSelectedFile(null)
    setPreviewUrl(s.signature_path ? `${API_BASE}/${s.signature_path}` : null)
    setModal({ open: true, editing: s })
  }

  const closeModal = () => {
    setModal({ open: false, editing: null })
    setSelectedFile(null)
    setPreviewUrl(null)
  }

  const validate = () => {
    const e: Partial<FormState & { signature_image: string }> = {}
    if (!form.label.trim()) e.label = 'Label wajib diisi.'
    if (!form.name.trim()) e.name = 'Nama wajib diisi.'
    setErrors(e)
    return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate() }

  return (
    <div>
      <PageHeader title="Tanda Tangan" subtitle="Kelola tanda tangan yang digunakan pada invoice dan kwitansi." onAdd={openAdd} />

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada tanda tangan." />}
          {(data ?? []).map(s => (
            <Card key={s.id}>
              {/* Signature image preview */}
              {s.signature_path ? (
                <div style={{ marginBottom: '0.75rem', textAlign: 'center', backgroundColor: '#f9fafb', borderRadius: 6, padding: '0.75rem', border: '1px solid #e5e7eb' }}>
                  <img
                    src={`${API_BASE}/${s.signature_path}`}
                    alt={`Tanda tangan ${s.name}`}
                    style={{ maxHeight: 80, maxWidth: '100%', objectFit: 'contain' }}
                    onError={e => { (e.target as HTMLImageElement).style.display = 'none' }}
                  />
                </div>
              ) : (
                <div style={{ marginBottom: '0.75rem', textAlign: 'center', backgroundColor: '#f9fafb', borderRadius: 6, padding: '1rem', border: '1px dashed #d1d5db', color: '#9ca3af', fontSize: '0.8125rem' }}>
                  Belum ada gambar
                </div>
              )}

              <div style={{ marginBottom: '0.5rem' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                  <span style={{ fontWeight: 600, color: '#111827' }}>{s.name}</span>
                  <Badge label={s.is_active ? 'Aktif' : 'Nonaktif'} color={s.is_active ? 'green' : 'gray'} />
                </div>
                <div style={{ fontSize: '0.8125rem', color: '#6b7280' }}>
                  {s.label}{s.position ? ` · ${s.position}` : ''}
                </div>
              </div>

              <div style={{ display: 'flex', gap: '0.5rem' }}>
                <Button variant="secondary" size="sm" onClick={() => openEdit(s)}>Ubah</Button>
                <Button variant="danger" size="sm" onClick={() => setDeleteTarget(s)}>Hapus</Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal open={modal.open} title={modal.editing ? 'Ubah Tanda Tangan' : 'Tambah Tanda Tangan'} onClose={closeModal}
        footer={
          <>
            <Button variant="secondary" onClick={closeModal}>Batal</Button>
            <Button onClick={handleSubmit} disabled={saveMutation.isPending}>
              {saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}
            </Button>
          </>
        }>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Input id="sig-label" label="Label *" value={form.label} onChange={set('label')} error={errors.label} placeholder="cth: Admin, Direktur" />
          <Input id="sig-name" label="Nama *" value={form.name} onChange={set('name')} error={errors.name} placeholder="Nama yang dicetak di bawah tanda tangan" />
          <Input id="sig-position" label="Jabatan" value={form.position} onChange={set('position')} placeholder="cth: Direktur Utama" />

          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.375rem' }}>
            <label style={{ fontSize: '0.875rem', fontWeight: 500, color: '#374151' }}>Status</label>
            <select value={form.is_active} onChange={set('is_active')}
              style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: '#111827' }}>
              <option value="1">Aktif</option>
              <option value="0">Nonaktif</option>
            </select>
          </div>

          {/* File upload */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.375rem' }}>
            <label style={{ fontSize: '0.875rem', fontWeight: 500, color: '#374151' }}>
              Gambar Tanda Tangan
              <span style={{ fontSize: '0.8125rem', color: '#6b7280', fontWeight: 400, marginLeft: '0.25rem' }}>(JPG/PNG/WebP, maks 2 MB)</span>
            </label>

            {/* Preview */}
            {previewUrl && (
              <div style={{ backgroundColor: '#f9fafb', borderRadius: 6, padding: '0.75rem', border: '1px solid #e5e7eb', textAlign: 'center', marginBottom: '0.25rem' }}>
                <img src={previewUrl} alt="Preview" style={{ maxHeight: 80, maxWidth: '100%', objectFit: 'contain' }} />
              </div>
            )}

            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <Button variant="secondary" size="sm" type="button" onClick={() => fileRef.current?.click()}>
                {previewUrl ? 'Ganti Gambar' : 'Pilih Gambar'}
              </Button>
              {selectedFile && (
                <span style={{ fontSize: '0.8125rem', color: '#6b7280' }}>{selectedFile.name}</span>
              )}
            </div>
            <input ref={fileRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleFileChange} />
            {errors.signature_image && (
              <span style={{ fontSize: '0.8125rem', color: '#dc2626' }} role="alert">{errors.signature_image}</span>
            )}
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus tanda tangan "${deleteTarget?.name}"?`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
