import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { ownershipApi } from '@/api/ownershipApi'
import { customerApi } from '@/api/customerApi'
import { projectApi } from '@/api/projectApi'
import { hierarchyApi } from '@/api/hierarchyApi'
import { pricingApi } from '@/api/pricingApi'
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
import Badge from '@/components/ui/Badge'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Ownership {
  id: number
  customer_id: number
  project_id: number
  cluster_id: number | null
  block_id: number | null
  lot_id: number | null
  billing_address: string | null
  area: string
  ipl_rate_id: number
  water_rate_group_id: number | null
  ownership_type: 'residential' | 'commercial'
  start_date: string
  end_date: string | null
  notes: string | null
  // enriched
  customer_name: string | null
  project_name: string | null
  project_type: string | null
  cluster_name: string | null
  block_name: string | null
  lot_number: string | null
  ipl_rate_name: string | null
  ipl_rate_per_sqm: string | null
  water_rate_group_name: string | null
}

interface FormState {
  customer_id: string
  project_id: string
  ownership_type: 'residential' | 'commercial' | ''
  cluster_id: string
  block_id: string
  lot_id: string
  billing_address: string
  area: string
  ipl_rate_id: string
  water_rate_group_id: string
  start_date: string
  end_date: string
  notes: string
}

const emptyForm: FormState = {
  customer_id: '', project_id: '', ownership_type: '',
  cluster_id: '', block_id: '', lot_id: '',
  billing_address: '', area: '', ipl_rate_id: '', water_rate_group_id: '',
  start_date: '', end_date: '', notes: '',
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const fmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 })
const fmtRp = (v: string | number | null) => v ? fmt.format(Number(v)) : '-'
const fmtDate = (d: string | null) => d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '-'

// ─── Main Component ───────────────────────────────────────────────────────────

export default function OwnershipPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  // List filters
  const [filterType, setFilterType] = useState('')
  const [filterCustomer, setFilterCustomer] = useState('')

  // Modal state
  const [modal, setModal] = useState<{ open: boolean; editing: Ownership | null }>({ open: false, editing: null })
  const [form, setForm] = useState<FormState>(emptyForm)
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof FormState, string>>>({})
  const [serverError, setServerError] = useState<string | null>(null)

  // Detail panel
  const [detailTarget, setDetailTarget] = useState<Ownership | null>(null)

  // Delete confirm
  const [deleteTarget, setDeleteTarget] = useState<Ownership | null>(null)

  // ── List data ───────────────────────────────────────────────────────────────
  const listParams: Record<string, unknown> = {}
  if (filterType) listParams.ownership_type = filterType
  if (filterCustomer) listParams.search = filterCustomer

  const { data: rawOwnerships, isLoading } = useQuery({
    queryKey: ['ownerships', listParams],
    queryFn: async () => {
      const res = await ownershipApi.list(listParams)
      const d = res.data?.data
      if (Array.isArray(d)) return d as Ownership[]
      if (Array.isArray(res.data)) return res.data as Ownership[]
      if (d && Array.isArray((d as any).data)) return (d as any).data as Ownership[]
      return [] as Ownership[]
    },
  })
  const ownerships = Array.isArray(rawOwnerships) ? rawOwnerships : []

  // ── Master data for form selects ─────────────────────────────────────────────
  const { data: rawCustomers } = useQuery({
    queryKey: ['customers', {}],
    queryFn: async () => {
      const res = await customerApi.list({})
      const d = res.data?.data
      if (Array.isArray(d)) return d as { id: number; name: string }[]
      if (Array.isArray(res.data)) return res.data as { id: number; name: string }[]
      if (d && Array.isArray((d as any).data)) return (d as any).data as { id: number; name: string }[]
      return [] as { id: number; name: string }[]
    },
  })
  const customers = Array.isArray(rawCustomers) ? rawCustomers : []

  const { data: rawProjects } = useQuery({
    queryKey: ['projects', {}],
    queryFn: async () => {
      const res = await projectApi.list({})
      const d = res.data?.data
      if (Array.isArray(d)) return d as { id: number; name: string; project_type: string }[]
      if (Array.isArray(res.data)) return res.data as { id: number; name: string; project_type: string }[]
      return [] as { id: number; name: string; project_type: string }[]
    },
  })
  const projects = Array.isArray(rawProjects) ? rawProjects : []

  // Derived: detect project type from selected project_id
  const selectedProject = projects.find(p => String(p.id) === form.project_id)
  const projectType = selectedProject?.project_type as 'residential' | 'commercial' | undefined

  // ── Dependent: clusters (only when residential project selected) ─────────────
  const { data: rawClusters } = useQuery({
    queryKey: ['clusters', form.project_id],
    queryFn: async () => {
      if (!form.project_id) return []
      const res = await hierarchyApi.listClusters({ project_id: form.project_id })
      const d = res.data?.data
      return Array.isArray(d) ? (d as { id: number; name: string }[]) : []
    },
    enabled: !!form.project_id && projectType === 'residential',
  })
  const clusters = Array.isArray(rawClusters) ? rawClusters : []

  // ── Dependent: blocks ────────────────────────────────────────────────────────
  const { data: rawBlocks } = useQuery({
    queryKey: ['blocks', form.cluster_id],
    queryFn: async () => {
      if (!form.cluster_id) return []
      const res = await hierarchyApi.listBlocks({ cluster_id: form.cluster_id })
      const d = res.data?.data
      return Array.isArray(d) ? (d as { id: number; name: string }[]) : []
    },
    enabled: !!form.cluster_id,
  })
  const blocks = Array.isArray(rawBlocks) ? rawBlocks : []

  // ── Dependent: lots ──────────────────────────────────────────────────────────
  const { data: rawLots } = useQuery({
    queryKey: ['lots', form.block_id],
    queryFn: async () => {
      if (!form.block_id) return []
      const res = await hierarchyApi.listLots({ block_id: form.block_id })
      const d = res.data?.data
      return Array.isArray(d) ? (d as { id: number; name?: string; lot_number: string; area: string | null }[]) : []
    },
    enabled: !!form.block_id,
  })
  const lots = Array.isArray(rawLots) ? rawLots : []

  // ── IPL rates filtered by project ────────────────────────────────────────────
  const { data: rawIplRates } = useQuery({
    queryKey: ['ipl-rates', form.project_id],
    queryFn: async () => {
      if (!form.project_id) return []
      const res = await pricingApi.listIplRates({ project_id: form.project_id })
      const d = res.data?.data
      return Array.isArray(d) ? (d as { id: number; name: string; rate_per_sqm: string }[]) : []
    },
    enabled: !!form.project_id,
  })
  const iplRates = Array.isArray(rawIplRates) ? rawIplRates : []

  // ── Water rate groups filtered by project ────────────────────────────────────
  const { data: rawWaterGroups } = useQuery({
    queryKey: ['water-rate-groups', form.project_id],
    queryFn: async () => {
      if (!form.project_id) return []
      const res = await pricingApi.listWaterGroups({ project_id: form.project_id })
      const d = res.data?.data
      return Array.isArray(d) ? (d as { id: number; name: string; abonemen: string }[]) : []
    },
    enabled: !!form.project_id,
  })
  const waterGroups = Array.isArray(rawWaterGroups) ? rawWaterGroups : []

  // ── Mutations ────────────────────────────────────────────────────────────────
  const saveMutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      modal.editing
        ? ownershipApi.update(modal.editing.id, payload)
        : ownershipApi.create(payload),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ownerships'] })
      closeModal()
      showToast(modal.editing ? 'Kepemilikan berhasil diperbarui.' : 'Kepemilikan berhasil dibuat.')
    },
    onError: (e: any) => {
      const msg = e.response?.data?.message ?? 'Terjadi kesalahan.'
      setServerError(msg)
      showToast(msg, 'error')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => ownershipApi.delete(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['ownerships'] })
      setDeleteTarget(null)
      showToast('Kepemilikan berhasil dihapus.')
    },
    onError: (e: any) => {
      setDeleteTarget(null)
      showToast(e.response?.data?.message ?? 'Gagal menghapus.', 'error')
    },
  })

  // ── Form helpers ──────────────────────────────────────────────────────────────
  const setField = (k: keyof FormState) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
      const v = e.target.value

      // When project changes: reset hierarchy and pricing fields, set ownership_type from project type
      if (k === 'project_id') {
        const proj = (projects ?? []).find(p => String(p.id) === v)
        setForm(f => ({
          ...f,
          project_id: v,
          ownership_type: (proj?.project_type ?? '') as any,
          cluster_id: '', block_id: '', lot_id: '',
          ipl_rate_id: '', water_rate_group_id: '',
        }))
        return
      }

      // When cluster changes: reset block and lot
      if (k === 'cluster_id') {
        setForm(f => ({ ...f, cluster_id: v, block_id: '', lot_id: '' }))
        return
      }

      // When block changes: reset lot
      if (k === 'block_id') {
        setForm(f => ({ ...f, block_id: v, lot_id: '' }))
        return
      }

      // When lot changes: auto-fill area from lot area
      if (k === 'lot_id') {
        const lot = (lots ?? []).find(l => String(l.id) === v)
        setForm(f => ({
          ...f,
          lot_id: v,
          area: lot?.area ? String(lot.area) : f.area,
        }))
        return
      }

      setForm(f => ({ ...f, [k]: v }))
    }

  const validate = (): boolean => {
    const e: Partial<Record<keyof FormState, string>> = {}
    if (!form.customer_id) e.customer_id = 'Customer wajib dipilih.'
    if (!form.project_id) e.project_id = 'Proyek wajib dipilih.'
    if (!form.area || Number(form.area) <= 0) e.area = 'Luas area harus lebih dari 0.'
    if (!form.ipl_rate_id) e.ipl_rate_id = 'Tarif IPL wajib dipilih.'
    if (!form.start_date) e.start_date = 'Tanggal mulai wajib diisi.'
    if (form.end_date && form.start_date && form.end_date < form.start_date) {
      e.end_date = 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.'
    }
    if (projectType === 'residential') {
      if (!form.cluster_id) e.cluster_id = 'Cluster wajib dipilih.'
      if (!form.block_id) e.block_id = 'Blok wajib dipilih.'
      if (!form.lot_id) e.lot_id = 'Kavling wajib dipilih.'
    }
    setFormErrors(e)
    return Object.keys(e).length === 0
  }

  const buildPayload = (): Record<string, unknown> => {
    const payload: Record<string, unknown> = {
      customer_id: Number(form.customer_id),
      project_id: Number(form.project_id),
      ownership_type: form.ownership_type,
      billing_address: form.billing_address || null,
      area: Number(form.area),
      ipl_rate_id: Number(form.ipl_rate_id),
      water_rate_group_id: form.water_rate_group_id ? Number(form.water_rate_group_id) : null,
      start_date: form.start_date,
      end_date: form.end_date || null,
      notes: form.notes || null,
    }
    if (projectType === 'residential') {
      payload.cluster_id = Number(form.cluster_id)
      payload.block_id = Number(form.block_id)
      payload.lot_id = Number(form.lot_id)
    }
    return payload
  }

  const handleSubmit = () => {
    setServerError(null)
    if (validate()) saveMutation.mutate(buildPayload())
  }

  const openAdd = () => {
    setForm(emptyForm)
    setFormErrors({})
    setServerError(null)
    setModal({ open: true, editing: null })
  }

  const openEdit = (o: Ownership) => {
    setForm({
      customer_id: String(o.customer_id),
      project_id: String(o.project_id),
      ownership_type: o.ownership_type,
      cluster_id: o.cluster_id ? String(o.cluster_id) : '',
      block_id: o.block_id ? String(o.block_id) : '',
      lot_id: o.lot_id ? String(o.lot_id) : '',
      billing_address: o.billing_address ?? '',
      area: o.area,
      ipl_rate_id: String(o.ipl_rate_id),
      water_rate_group_id: o.water_rate_group_id ? String(o.water_rate_group_id) : '',
      start_date: o.start_date,
      end_date: o.end_date ?? '',
      notes: o.notes ?? '',
    })
    setFormErrors({})
    setServerError(null)
    setModal({ open: true, editing: o })
  }

  const closeModal = () => setModal({ open: false, editing: null })

  // ── Select options ────────────────────────────────────────────────────────────
  const customerOptions = (customers ?? []).map(c => ({ value: c.id, label: c.name }))
  const projectOptions  = (projects ?? []).map(p => ({ value: p.id, label: `${p.name} (${p.project_type === 'residential' ? 'Residensial' : 'Komersial'})` }))
  const clusterOptions  = (clusters ?? []).map(c => ({ value: c.id, label: c.name }))
  const blockOptions    = (blocks ?? []).map(b => ({ value: b.id, label: b.name }))
  const lotOptions      = (lots ?? []).map(l => ({ value: l.id, label: `${l.lot_number}${l.area ? ` (${l.area} m²)` : ''}` }))
  const iplOptions      = (iplRates ?? []).map(r => ({ value: r.id, label: `${r.name} — ${fmtRp(r.rate_per_sqm)}/m²` }))
  const waterOptions    = (waterGroups ?? []).map(w => ({ value: w.id, label: w.name }))

  const isResidential = projectType === 'residential'
  const isCommercial  = projectType === 'commercial'

  // ── Status badge ──────────────────────────────────────────────────────────────
  const statusBadge = (o: Ownership) => {
    if (!o.end_date) return <Badge label="Aktif" color="green" />
    if (new Date(o.end_date) < new Date()) return <Badge label="Selesai" color="gray" />
    return <Badge label="Aktif" color="green" />
  }

  return (
    <div>
      <PageHeader title="Kepemilikan" subtitle="Kelola kepemilikan properti oleh customer." onAdd={openAdd} />

      {/* Filters */}
      <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap' }}>
        <input
          value={filterCustomer}
          onChange={e => setFilterCustomer(e.target.value)}
          placeholder="Cari nama customer..."
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: '#111827', width: 220 }}
        />
        <select
          value={filterType}
          onChange={e => setFilterType(e.target.value)}
          style={{ padding: '0.5rem 0.75rem', fontSize: '0.9375rem', border: '1px solid #d1d5db', borderRadius: 6, backgroundColor: '#fff', color: filterType ? '#111827' : '#9ca3af' }}
        >
          <option value="">Semua Tipe</option>
          <option value="residential">Residensial</option>
          <option value="commercial">Komersial</option>
        </select>
      </div>

      {/* List */}
      {isLoading ? <Spinner /> : (
        <>
          {(ownerships ?? []).length === 0 && <EmptyState message="Belum ada data kepemilikan." />}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            {(ownerships ?? []).map(o => (
              <Card key={o.id}>
                <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: '0.5rem' }}>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem', flexWrap: 'wrap' }}>
                      <span style={{ fontWeight: 600, color: '#111827', fontSize: '0.9375rem' }}>{o.customer_name ?? '-'}</span>
                      <Badge label={o.ownership_type === 'residential' ? 'Residensial' : 'Komersial'} color={o.ownership_type === 'residential' ? 'green' : 'blue'} />
                      {statusBadge(o)}
                    </div>
                    <div style={{ fontSize: '0.875rem', color: '#374151', marginBottom: '0.125rem' }}>
                      <strong>{o.project_name ?? '-'}</strong>
                      {o.ownership_type === 'residential' && o.cluster_name &&
                        <span style={{ color: '#6b7280' }}> · {o.cluster_name} · {o.block_name} · {o.lot_number}</span>
                      }
                    </div>
                    <div style={{ fontSize: '0.8125rem', color: '#6b7280', display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
                      <span>Luas: {o.area} m²</span>
                      <span>IPL: {o.ipl_rate_name}</span>
                      {o.water_rate_group_name && <span>Air: {o.water_rate_group_name}</span>}
                      <span>Mulai: {fmtDate(o.start_date)}</span>
                    </div>
                  </div>
                  <div style={{ display: 'flex', gap: '0.5rem', flexShrink: 0 }}>
                    <Button variant="ghost" size="sm" onClick={() => setDetailTarget(o)}>Detail</Button>
                    <Button variant="secondary" size="sm" onClick={() => openEdit(o)}>Ubah</Button>
                    <Button variant="danger" size="sm" onClick={() => setDeleteTarget(o)}>Hapus</Button>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        </>
      )}

      {/* Create/Edit Modal */}
      <Modal
        open={modal.open}
        title={modal.editing ? 'Ubah Kepemilikan' : 'Tambah Kepemilikan'}
        onClose={closeModal}
        width={600}
        footer={
          <>
            <Button variant="secondary" onClick={closeModal}>Batal</Button>
            <Button onClick={handleSubmit} disabled={saveMutation.isPending}>
              {saveMutation.isPending ? 'Menyimpan...' : 'Simpan'}
            </Button>
          </>
        }
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {serverError && (
            <div style={{ backgroundColor: '#fef2f2', border: '1px solid #fecaca', color: '#b91c1c', borderRadius: 6, padding: '0.75rem 1rem', fontSize: '0.875rem' }} role="alert">
              {serverError}
            </div>
          )}

          {/* Customer */}
          <Select id="own-customer" label="Customer *" value={form.customer_id}
            onChange={setField('customer_id')} options={customerOptions}
            placeholder="Pilih customer..." error={formErrors.customer_id} />

          {/* Project */}
          <Select id="own-project" label="Proyek *" value={form.project_id}
            onChange={setField('project_id')} options={projectOptions}
            placeholder="Pilih proyek..." error={formErrors.project_id} />

          {/* Project type indicator */}
          {form.project_id && (
            <div style={{ fontSize: '0.8125rem', color: '#6b7280', padding: '0.25rem 0', borderLeft: '3px solid #2d5a3d', paddingLeft: '0.625rem' }}>
              Tipe proyek: <strong>{isResidential ? 'Residensial' : isCommercial ? 'Komersial' : '-'}</strong>
              {isResidential && ' — Wajib memilih Cluster, Blok, dan Kavling.'}
              {isCommercial && ' — Tanpa hierarki Cluster/Blok/Kavling.'}
            </div>
          )}

          {/* Residential: Cluster → Block → Lot */}
          {isResidential && (
            <>
              <Select id="own-cluster" label="Cluster *" value={form.cluster_id}
                onChange={setField('cluster_id')} options={clusterOptions}
                placeholder={clusterOptions.length === 0 ? 'Tidak ada cluster tersedia' : 'Pilih cluster...'}
                error={formErrors.cluster_id} />

              <Select id="own-block" label="Blok *" value={form.block_id}
                onChange={setField('block_id')} options={blockOptions}
                placeholder={form.cluster_id ? (blockOptions.length === 0 ? 'Tidak ada blok' : 'Pilih blok...') : 'Pilih cluster dulu'}
                error={formErrors.block_id} />

              <Select id="own-lot" label="Kavling *" value={form.lot_id}
                onChange={setField('lot_id')} options={lotOptions}
                placeholder={form.block_id ? (lotOptions.length === 0 ? 'Tidak ada kavling' : 'Pilih kavling...') : 'Pilih blok dulu'}
                error={formErrors.lot_id} />
            </>
          )}

          {/* Billing Address */}
          <Textarea id="own-addr" label="Alamat Penagihan" value={form.billing_address}
            onChange={setField('billing_address')} rows={2} />

          {/* Area */}
          <Input id="own-area" label="Luas Area (m²) *" type="number" min="0.01" step="0.01"
            value={form.area} onChange={setField('area')} error={formErrors.area} />

          {/* IPL Rate */}
          <Select id="own-ipl" label="Tarif IPL *" value={form.ipl_rate_id}
            onChange={setField('ipl_rate_id')} options={iplOptions}
            placeholder={form.project_id ? (iplOptions.length === 0 ? 'Tidak ada tarif IPL untuk proyek ini' : 'Pilih tarif IPL...') : 'Pilih proyek dulu'}
            error={formErrors.ipl_rate_id} />

          {/* Water Rate Group (optional) */}
          <Select id="own-water" label="Paket Tarif Air" value={form.water_rate_group_id}
            onChange={setField('water_rate_group_id')} options={waterOptions}
            placeholder="Tidak menggunakan tarif air" />

          {/* Start / End dates */}
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.75rem' }}>
            <Input id="own-start" label="Tanggal Mulai *" type="date"
              value={form.start_date} onChange={setField('start_date')} error={formErrors.start_date} />
            <Input id="own-end" label="Tanggal Selesai" type="date"
              value={form.end_date} onChange={setField('end_date')} error={formErrors.end_date} />
          </div>

          {/* Notes */}
          <Textarea id="own-notes" label="Catatan" value={form.notes}
            onChange={setField('notes')} rows={2} />
        </div>
      </Modal>

      {/* Detail Modal */}
      {detailTarget && (
        <Modal open={!!detailTarget} title="Detail Kepemilikan" onClose={() => setDetailTarget(null)} width={520}
          footer={
            <>
              <Button variant="secondary" onClick={() => setDetailTarget(null)}>Tutup</Button>
              <Button onClick={() => { setDetailTarget(null); openEdit(detailTarget) }}>Ubah</Button>
            </>
          }
        >
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.875rem' }}>
            <Section label="Customer">
              {detailTarget.customer_name ?? '-'}
            </Section>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.875rem' }}>
              <Section label="Proyek">{detailTarget.project_name ?? '-'}</Section>
              <Section label="Tipe">
                <Badge label={detailTarget.ownership_type === 'residential' ? 'Residensial' : 'Komersial'}
                  color={detailTarget.ownership_type === 'residential' ? 'green' : 'blue'} />
              </Section>
            </div>
            {detailTarget.ownership_type === 'residential' && (
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '0.875rem' }}>
                <Section label="Cluster">{detailTarget.cluster_name ?? '-'}</Section>
                <Section label="Blok">{detailTarget.block_name ?? '-'}</Section>
                <Section label="Kavling">{detailTarget.lot_number ?? '-'}</Section>
              </div>
            )}
            <Section label="Alamat Penagihan">{detailTarget.billing_address ?? '-'}</Section>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.875rem' }}>
              <Section label="Luas Area">{detailTarget.area} m²</Section>
              <Section label="Tarif IPL">{detailTarget.ipl_rate_name ?? '-'} ({fmtRp(detailTarget.ipl_rate_per_sqm)}/m²)</Section>
            </div>
            {detailTarget.water_rate_group_name && (
              <Section label="Paket Tarif Air">{detailTarget.water_rate_group_name}</Section>
            )}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.875rem' }}>
              <Section label="Tanggal Mulai">{fmtDate(detailTarget.start_date)}</Section>
              <Section label="Tanggal Selesai">{fmtDate(detailTarget.end_date)}</Section>
            </div>
            <Section label="Status">
              {!detailTarget.end_date ? <Badge label="Aktif" color="green" /> : <Badge label="Selesai" color="gray" />}
            </Section>
            {detailTarget.notes && <Section label="Catatan">{detailTarget.notes}</Section>}
          </div>
        </Modal>
      )}

      {/* Delete confirm */}
      <ConfirmDialog
        open={!!deleteTarget}
        message={`Hapus kepemilikan "${deleteTarget?.customer_name}" di "${deleteTarget?.project_name}"? Tindakan ini tidak dapat dibatalkan.`}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
        loading={deleteMutation.isPending}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}

// ─── Helper component ─────────────────────────────────────────────────────────

function Section({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <div style={{ fontSize: '0.75rem', fontWeight: 600, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '0.25rem' }}>{label}</div>
      <div style={{ fontSize: '0.9375rem', color: '#111827' }}>{children}</div>
    </div>
  )
}
