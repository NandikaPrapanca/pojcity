import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { pricingApi } from '@/api/pricingApi'
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

interface Project { id: number; name: string }
interface WaterRateGroup { id: number; name: string; abonemen: number; project_id: number; project?: { name: string } }
interface WaterRateTier { id: number; min_usage: number; max_usage?: number | null; rate_per_m3: number }

interface GroupFormState { project_id: string; name: string; abonemen: string }
const emptyGroup: GroupFormState = { project_id: '', name: '', abonemen: '0' }

interface TierFormState { min_usage: string; max_usage: string; rate_per_m3: string }
const emptyTier: TierFormState = { min_usage: '', max_usage: '', rate_per_m3: '' }

const formatRupiah = (amount: number) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)

export default function WaterRateGroupPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  const [filterProjectId, setFilterProjectId] = useState('')
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [modal, setModal] = useState<{ open: boolean; editing: WaterRateGroup | null }>({ open: false, editing: null })
  const [form, setForm] = useState<GroupFormState>(emptyGroup)
  const [errors, setErrors] = useState<Partial<GroupFormState>>({})
  const [deleteTarget, setDeleteTarget] = useState<WaterRateGroup | null>(null)

  // Tier state per expanded group
  const [tierForm, setTierForm] = useState<TierFormState>(emptyTier)
  const [tierErrors, setTierErrors] = useState<Partial<TierFormState>>({})
  const [deleteTierTarget, setDeleteTierTarget] = useState<WaterRateTier | null>(null)

  const { data: projects } = useQuery({
    queryKey: ['projects', {}],
    queryFn: () => projectApi.list().then(r => r.data.data as Project[]),
  })
  const projectOptions = (projects ?? []).map(p => ({ value: p.id, label: p.name }))

  const params: Record<string, unknown> = {}
  if (filterProjectId) params.project_id = filterProjectId

  const { data, isLoading } = useQuery({
    queryKey: ['water-rate-groups', params],
    queryFn: () => pricingApi.listWaterGroups(params).then(r => r.data.data as WaterRateGroup[]),
  })

  const { data: tiers } = useQuery({
    queryKey: ['water-rate-tiers', expandedId],
    queryFn: () => expandedId ? pricingApi.groupTiers(expandedId).then(r => r.data.data as WaterRateTier[]) : Promise.resolve([]),
    enabled: expandedId !== null,
  })

  const saveMutation = useMutation({
    mutationFn: (payload: GroupFormState) => {
      const body = { project_id: Number(payload.project_id), name: payload.name, abonemen: parseFloat(payload.abonemen) || 0 }
      return modal.editing ? pricingApi.updateWaterGroup(modal.editing.id, body) : pricingApi.createWaterGroup(body)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['water-rate-groups'] }); closeModal(); showToast('Grup tarif air berhasil disimpan.') },
    onError: (e: any) => { setErrors(e.response?.data?.errors ?? {}); showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error') },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => pricingApi.deleteWaterGroup(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['water-rate-groups'] }); setDeleteTarget(null); showToast('Grup tarif air berhasil dihapus.') },
    onError: (e: any) => { setDeleteTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const createTierMutation = useMutation({
    mutationFn: (payload: TierFormState) => {
      if (!expandedId) throw new Error('No group')
      const body: Record<string, unknown> = { min_usage: parseFloat(payload.min_usage), rate_per_m3: parseFloat(payload.rate_per_m3) }
      if (payload.max_usage) body.max_usage = parseFloat(payload.max_usage)
      return pricingApi.createTier(expandedId, body)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['water-rate-tiers', expandedId] })
      setTierForm(emptyTier)
      setTierErrors({})
      showToast('Tier berhasil ditambahkan.')
    },
    onError: (e: any) => {
      setTierErrors(e.response?.data?.errors ?? {})
      showToast(e.response?.data?.message ?? 'Terjadi kesalahan.', 'error')
    },
  })

  const deleteTierMutation = useMutation({
    mutationFn: (id: number) => pricingApi.deleteTier(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['water-rate-tiers', expandedId] })
      setDeleteTierTarget(null)
      showToast('Tier berhasil dihapus.')
    },
    onError: (e: any) => { setDeleteTierTarget(null); showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error') },
  })

  const set = (k: keyof GroupFormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm(f => ({ ...f, [k]: e.target.value }))
  const setTier = (k: keyof TierFormState) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setTierForm(f => ({ ...f, [k]: e.target.value }))

  const openAdd = () => { setForm(emptyGroup); setErrors({}); setModal({ open: true, editing: null }) }
  const openEdit = (g: WaterRateGroup) => {
    setForm({ project_id: String(g.project_id), name: g.name, abonemen: String(g.abonemen) })
    setErrors({}); setModal({ open: true, editing: g })
  }
  const closeModal = () => setModal({ open: false, editing: null })
  const validate = () => {
    const e: Partial<GroupFormState> = {}
    if (!form.project_id) e.project_id = 'Proyek wajib dipilih.'
    if (!form.name.trim()) e.name = 'Nama wajib diisi.'
    setErrors(e); return Object.keys(e).length === 0
  }
  const handleSubmit = () => { if (validate()) saveMutation.mutate(form) }

  const validateTier = () => {
    const e: Partial<TierFormState> = {}
    if (!tierForm.min_usage || isNaN(parseFloat(tierForm.min_usage))) e.min_usage = 'Wajib diisi.'
    if (!tierForm.rate_per_m3 || isNaN(parseFloat(tierForm.rate_per_m3))) e.rate_per_m3 = 'Wajib diisi.'
    setTierErrors(e); return Object.keys(e).length === 0
  }
  const handleAddTier = () => { if (validateTier()) createTierMutation.mutate(tierForm) }

  const toggleExpand = (id: number) => {
    setExpandedId(p => p === id ? null : id)
    setTierForm(emptyTier)
    setTierErrors({})
  }

  return (
    <div>
      <PageHeader title="Tarif Air" subtitle="Kelola grup tarif air dan tier penggunaan." onAdd={openAdd} />

      <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
        <select value={filterProjectId} onChange={e => setFilterProjectId(e.target.value)}
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: filterProjectId ? '#111827' : '#9ca3af' }}>
          <option value="">Semua Proyek</option>
          {projectOptions.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </div>

      {isLoading ? <Spinner /> : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
          {(data ?? []).length === 0 && <EmptyState message="Belum ada grup tarif air." />}
          {(data ?? []).map(g => (
            <Card key={g.id}>
              <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
                <div>
                  <div style={{ fontWeight: 600, color: '#111827' }}>{g.name}</div>
                  <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>
                    Abonemen: {formatRupiah(g.abonemen)}
                    {g.project?.name && ` · Proyek: ${g.project.name}`}
                  </div>
                </div>
                <div style={{ display: 'flex', gap: '0.5rem' }}>
                  <Button variant="secondary" size="sm" onClick={() => toggleExpand(g.id)}>
                    {expandedId === g.id ? 'Tutup Tier' : 'Kelola Tier'}
                  </Button>
                  <Button variant="secondary" size="sm" onClick={() => openEdit(g)}>Ubah</Button>
                  <Button variant="danger" size="sm" onClick={() => setDeleteTarget(g)}>Hapus</Button>
                </div>
              </div>

              {/* Tier panel */}
              {expandedId === g.id && (
                <div style={{ marginTop: '1rem', borderTop: '1px solid #e5e7eb', paddingTop: '1rem' }}>
                  <div style={{ fontWeight: 600, fontSize: '0.875rem', color: '#374151', marginBottom: '0.75rem' }}>Tier Penggunaan</div>

                  {/* Tier table */}
                  {(tiers ?? []).length === 0 && (
                    <div style={{ color: '#9ca3af', fontSize: '0.875rem', marginBottom: '0.75rem' }}>Belum ada tier.</div>
                  )}
                  {(tiers ?? []).length > 0 && (
                    <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: '1rem', fontSize: '0.875rem' }}>
                      <thead>
                        <tr style={{ backgroundColor: '#f9fafb' }}>
                          <th style={{ padding: '0.5rem 0.75rem', textAlign: 'left', color: '#6b7280', fontWeight: 500 }}>Min (m³)</th>
                          <th style={{ padding: '0.5rem 0.75rem', textAlign: 'left', color: '#6b7280', fontWeight: 500 }}>Maks (m³)</th>
                          <th style={{ padding: '0.5rem 0.75rem', textAlign: 'left', color: '#6b7280', fontWeight: 500 }}>Tarif/m³</th>
                          <th style={{ padding: '0.5rem 0.75rem', textAlign: 'right', color: '#6b7280', fontWeight: 500 }}>Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(tiers ?? []).map(t => (
                          <tr key={t.id} style={{ borderTop: '1px solid #f3f4f6' }}>
                            <td style={{ padding: '0.5rem 0.75rem', color: '#111827' }}>{t.min_usage}</td>
                            <td style={{ padding: '0.5rem 0.75rem', color: '#111827' }}>{t.max_usage ?? '∞'}</td>
                            <td style={{ padding: '0.5rem 0.75rem', color: '#111827' }}>{formatRupiah(t.rate_per_m3)}</td>
                            <td style={{ padding: '0.5rem 0.75rem', textAlign: 'right' }}>
                              <Button variant="danger" size="sm" onClick={() => setDeleteTierTarget(t)}>Hapus</Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}

                  {/* Add tier form */}
                  <div style={{ backgroundColor: '#f9fafb', borderRadius: 6, padding: '0.875rem', border: '1px solid #e5e7eb' }}>
                    <div style={{ fontWeight: 500, fontSize: '0.8125rem', color: '#374151', marginBottom: '0.75rem' }}>Tambah Tier</div>
                    <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap', alignItems: 'flex-end' }}>
                      <div style={{ flex: '1 1 100px' }}>
                        <label style={{ display: 'block', fontSize: '0.8125rem', fontWeight: 500, color: '#374151', marginBottom: '0.25rem' }}>Min (m³) *</label>
                        <input type="number" min="0" step="0.01" value={tierForm.min_usage} onChange={setTier('min_usage')}
                          style={{ padding: '0.4rem 0.6rem', fontSize: '0.875rem', border: `1px solid ${tierErrors.min_usage ? '#dc2626' : '#d1d5db'}`, borderRadius: 6, width: '100%', boxSizing: 'border-box' }} />
                        {tierErrors.min_usage && <span style={{ fontSize: '0.75rem', color: '#dc2626' }}>{tierErrors.min_usage}</span>}
                      </div>
                      <div style={{ flex: '1 1 100px' }}>
                        <label style={{ display: 'block', fontSize: '0.8125rem', fontWeight: 500, color: '#374151', marginBottom: '0.25rem' }}>Maks (m³)</label>
                        <input type="number" min="0" step="0.01" value={tierForm.max_usage} onChange={setTier('max_usage')}
                          placeholder="Kosong = tak terbatas"
                          style={{ padding: '0.4rem 0.6rem', fontSize: '0.875rem', border: '1px solid #d1d5db', borderRadius: 6, width: '100%', boxSizing: 'border-box' }} />
                      </div>
                      <div style={{ flex: '1 1 120px' }}>
                        <label style={{ display: 'block', fontSize: '0.8125rem', fontWeight: 500, color: '#374151', marginBottom: '0.25rem' }}>Tarif/m³ (Rp) *</label>
                        <input type="number" min="0" step="0.01" value={tierForm.rate_per_m3} onChange={setTier('rate_per_m3')}
                          style={{ padding: '0.4rem 0.6rem', fontSize: '0.875rem', border: `1px solid ${tierErrors.rate_per_m3 ? '#dc2626' : '#d1d5db'}`, borderRadius: 6, width: '100%', boxSizing: 'border-box' }} />
                        {tierErrors.rate_per_m3 && <span style={{ fontSize: '0.75rem', color: '#dc2626' }}>{tierErrors.rate_per_m3}</span>}
                      </div>
                      <Button onClick={handleAddTier} disabled={createTierMutation.isPending} size="sm">
                        {createTierMutation.isPending ? 'Menyimpan...' : 'Tambah'}
                      </Button>
                    </div>
                  </div>
                </div>
              )}
            </Card>
          ))}
        </div>
      )}

      {/* Group modal */}
      <Modal open={modal.open} title={modal.editing ? 'Ubah Grup Tarif Air' : 'Tambah Grup Tarif Air'} onClose={closeModal}
        footer={<><Button variant="secondary" onClick={closeModal}>Batal</Button><Button onClick={handleSubmit} disabled={saveMutation.isPending}>{saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <Select id="wg-project" label="Proyek *" value={form.project_id} onChange={set('project_id')} options={projectOptions} placeholder="Pilih proyek..." error={errors.project_id} />
          <Input id="wg-name" label="Nama Grup *" value={form.name} onChange={set('name')} error={errors.name} />
          <Input id="wg-abonemen" label="Abonemen (Rp)" type="number" min="0" value={form.abonemen} onChange={set('abonemen')} />
        </div>
      </Modal>

      {/* Delete group confirm */}
      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus grup tarif air "${deleteTarget?.name}"? Semua tier akan ikut terhapus.`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {/* Delete tier confirm */}
      <ConfirmDialog
        open={!!deleteTierTarget}
        message={`Hapus tier min=${deleteTierTarget?.min_usage} – maks=${deleteTierTarget?.max_usage ?? '∞'}?`}
        onConfirm={() => deleteTierTarget && deleteTierMutation.mutate(deleteTierTarget.id)}
        onCancel={() => setDeleteTierTarget(null)}
        loading={deleteTierMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}
