import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { taxApi } from '@/api/taxApi'
import { useToast } from '@/hooks/useToast'
import PageHeader from '@/components/ui/PageHeader'
import Card from '@/components/ui/Card'
import Button from '@/components/ui/Button'
import Input from '@/components/ui/Input'
import Modal from '@/components/ui/Modal'
import Spinner from '@/components/ui/Spinner'
import Toast from '@/components/ui/Toast'
import Badge from '@/components/ui/Badge'
import EmptyState from '@/components/ui/EmptyState'

interface TaxConfiguration {
  id: number
  label: string
  dpp_multiplier_numerator: number
  dpp_multiplier_denominator: number
  ppn_rate: number
  effective_date: string
  is_active: boolean
}

interface FormState {
  label: string
  dpp_multiplier_numerator: string
  dpp_multiplier_denominator: string
  ppn_rate: string
  effective_date: string
}
const empty: FormState = { label: '', dpp_multiplier_numerator: '11', dpp_multiplier_denominator: '12', ppn_rate: '0.12', effective_date: '' }

const formatDate = (dateStr: string) => {
  try { return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }
  catch { return dateStr }
}

const formatPercent = (rate: number) => `${(rate * 100).toFixed(0)}%`

export default function TaxConfigurationPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  const [modal, setModal] = useState<{ open: boolean; editing: TaxConfiguration | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(empty)
  const [errors, setErrors] = useState<Partial<FormState>>({})

  const { data, isLoading } = useQuery({
    queryKey: ['tax-configurations'],
    queryFn: () => taxApi.list().then(r => r.data.data as TaxConfiguration[]),
  })

  const saveMutation = useMutation({
    mutationFn: (payload: FormState) => {
      const body = {
        label: payload.label,
        dpp_multiplier_numerator: parseInt(payload.dpp_multiplier_numerator),
        dpp_multiplier_denominator: parseInt(payload.dpp_multiplier_denominator),
        ppn_rate: parseFloat(payload.ppn_rate),
        effective_date: payload.effective_date,
      }
      return modal.editing ? taxApi.update(modal.editing.id, body) : taxApi.create(body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['tax-configurations'] }); closeModal(); showToast('Konfigurasi pajak berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const activateMutation = useMutation({
    mutationFn: (id: number) => taxApi.activate(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['tax-configurations'] }); showToast('Konfigurasi pajak diaktifkan.') },
    onError: (e: any) => { showToast(e.response?.data?.message ?? 'Gagal mengaktifkan.', 'error') },
  })

  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))

  const openAdd = () => { setForm(empty); setErrors({}); setModal({ open: true, editing: null }) }
  const openEdit = (t: TaxConfiguration) => {
    setForm({
      label: t.label,
      dpp_multiplier_numerator: String(t.dpp_multiplier_numerator),
      dpp_multiplier_denominator: String(t.dpp_multiplier_denominator),
      ppn_rate: String(t.ppn_rate),
      effective_date: t.effective_date?.substring(0, 10) ?? '',
    })
    setErrors({}); setModal({ open: true, editing: t })
  }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => {
    const e: Partial<FormState> = {}
    if (!form.label.trim()) e.label = 'Label wajib diisi.'
    if (!form.dpp_multiplier_numerator || isNaN(parseInt(form.dpp_multiplier_numerator))) e.dpp_multiplier_numerator = 'Wajib diisi.'
    if (!form.dpp_multiplier_denominator || isNaN(parseInt(form.dpp_multiplier_denominator))) e.dpp_multiplier_denominator = 'Wajib diisi.'
    if (!form.ppn_rate || isNaN(parseFloat(form.ppn_rate))) e.ppn_rate = 'Wajib diisi.'
    if (!form.effective_date) e.effective_date = 'Tanggal berlaku wajib diisi.'
    setErrors(e); return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  return (
    <div>
      <PageHeader title="Konfigurasi Pajak" subtitle="Kelola konfigurasi PPN dan DPP." onAdd={openAdd} />

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada konfigurasi pajak." />}
          {(data ?? []).map(t => (
            <Card key={t.id} style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                  <span style={{ fontWeight: 600, color: '#111827' }}>{t.label}</span>
                  <Badge label={t.is_active ? 'Aktif' : 'Tidak Aktif'} color={t.is_active ? 'green' : 'gray'} />
                </div>
                <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>
                  PPN: {formatPercent(t.ppn_rate)} · DPP: {t.dpp_multiplier_numerator}/{t.dpp_multiplier_denominator} · Berlaku: {formatDate(t.effective_date)}
                </div>
              </div>
              <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                {!t.is_active && (
                  <Button variant="secondary" size="sm" onClick={() => activateMutation.mutate(t.id)} disabled={activateMutation.isPending}>
                    Aktifkan
                  </Button>
                )}
                <Button variant="secondary" size="sm" onClick={() => openEdit(t)}>Ubah</Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal open={modal.open} title={modal.editing ? 'Ubah Konfigurasi Pajak' : 'Tambah Konfigurasi Pajak'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Input id="tax-label" label="Label *" value={form.label} onChange={set('label')} placeholder="Contoh: PPN 12% (PMK 2025)" error={errors.label} />
          <div style={{ display: 'flex', gap: '0.75rem' }}>
            <div style={{ flex: 1 }}>
              <Input id="tax-num" label="DPP Numerator *" type="number" min="1" value={form.dpp_multiplier_numerator} onChange={set('dpp_multiplier_numerator')} error={errors.dpp_multiplier_numerator} />
            </div>
            <div style={{ flex: 1 }}>
              <Input id="tax-den" label="DPP Denominator *" type="number" min="1" value={form.dpp_multiplier_denominator} onChange={set('dpp_multiplier_denominator')} error={errors.dpp_multiplier_denominator} />
            </div>
          </div>
          <p style={{ margin: 0, fontSize: '0.8125rem', color: '#6b7280' }}>
            DPP = Harga × {form.dpp_multiplier_numerator}/{form.dpp_multiplier_denominator}
          </p>
          <Input id="tax-rate" label="Tarif PPN *" type="number" min="0" step="0.01" value={form.ppn_rate} onChange={set('ppn_rate')} placeholder="Contoh: 0.12" error={errors.ppn_rate} />
          <p style={{ margin: 0, fontSize: '0.8125rem', color: '#6b7280' }}>
            Masukkan nilai desimal, contoh: 0.12 = 12%
          </p>
          <Input id="tax-date" label="Tanggal Berlaku *" type="date" value={form.effective_date} onChange={set('effective_date')} error={errors.effective_date} />
          {!modal.editing && (
            <p style={{ margin: 0, fontSize: '0.8125rem', color: '#6b7280', backgroundColor: '#f9fafb', padding: '0.5rem 0.75rem', borderRadius: 6 }}>
              ℹ️ Konfigurasi baru dibuat dengan status tidak aktif. Gunakan tombol "Aktifkan" untuk menggunakannya.
            </p>
          )}
        </div>
      </Modal>

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
