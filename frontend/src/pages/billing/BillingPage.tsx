import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { billingApi, type BillingItemPayload } from '@/api/billingApi'
import { ownershipApi } from '@/api/ownershipApi'
// IplGeneratePayload is used by generateIpl mutation below
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

// ─── Interfaces ───────────────────────────────────────────────────────────────

interface BillingItem {
  id: number
  ownership_id: number
  billing_type: 'ipl' | 'water' | 'electricity' | 'other'
  billing_period_start: string
  billing_period_end: string
  meter_reading_id?: number | null
  description: string
  quantity: string | number
  unit: string
  unit_price: string | number
  subtotal: string | number
  management_fee_rate?: string | number | null
  management_fee_amount?: string | number | null
  pln_charge?: string | number | null
  apply_tax: number | boolean
  notes?: string | null
  status: 'draft' | 'invoiced' | 'cancelled'
  created_at: string
  updated_at: string
  // relations
  customer_id?: number | null
  customer_name?: string | null
  customer_type?: string | null
  customer_whatsapp?: string | null
  customer_email?: string | null
  project_id?: number | null
  project_name?: string | null
  project_type?: 'residential' | 'commercial' | null
  ownership_type?: 'residential' | 'commercial' | null
  cluster_name?: string | null
  block_name?: string | null
  lot_number?: string | null
  area?: string | number | null
  billing_address?: string | null
}

interface OwnershipOption {
  id: number
  customer_name: string | null
  project_name: string | null
  ownership_type: 'residential' | 'commercial'
  cluster_name: string | null
  block_name: string | null
  lot_number: string | null
  area: string | number | null
  billing_address: string | null
  ipl_rate_id?: number | null
  ipl_rate_name?: string | null
  ipl_rate_per_sqm?: string | number | null
  water_rate_group_id?: number | null
  water_rate_group_name?: string | null
}

interface IplGenForm {
  ownership_id: string
  billing_period_start: string
  billing_period_end: string
  notes: string
}

const emptyIplForm: IplGenForm = {
  ownership_id: '',
  billing_period_start: '',
  billing_period_end: '',
  notes: '',
}

interface WaterGenForm {
  ownership_id: string
  billing_period_start: string
  billing_period_end: string
  notes: string
}

const emptyWaterForm: WaterGenForm = {
  ownership_id: '',
  billing_period_start: '',
  billing_period_end: '',
  notes: '',
}

interface FormState {
  ownership_id: string
  billing_type: 'ipl' | 'water' | 'electricity' | 'other'
  billing_period_start: string
  billing_period_end: string
  description: string
  quantity: string
  unit: string
  unit_price: string
  apply_tax: boolean
  notes: string
}

const emptyForm: FormState = {
  ownership_id: '',
  billing_type: 'ipl',
  billing_period_start: '',
  billing_period_end: '',
  description: '',
  quantity: '1',
  unit: 'ls',
  unit_price: '0',
  apply_tax: true,
  notes: '',
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const formatRupiah = (val: string | number | null | undefined): string => {
  if (val === null || val === undefined || val === '') return 'Rp 0'
  const num = Number(val)
  return 'Rp ' + Math.round(num).toLocaleString('id-ID')
}

const formatNumber = (val: string | number | null | undefined): string => {
  if (val === null || val === undefined || val === '') return '0'
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

const getTypeBadgeColor = (type: string): 'green' | 'gray' | 'blue' | 'red' => {
  switch (type.toLowerCase()) {
    case 'ipl': return 'blue'
    case 'water': return 'green'
    case 'electricity': return 'gray'
    case 'other': return 'gray'
    default: return 'gray'
  }
}

const getTypeLabel = (type: string): string => {
  switch (type.toLowerCase()) {
    case 'ipl': return 'IPL'
    case 'water': return 'Air'
    case 'electricity': return 'Listrik'
    case 'other': return 'Lain-lain'
    default: return type.toUpperCase()
  }
}

const getStatusBadgeColor = (status: string): 'green' | 'gray' | 'blue' | 'red' => {
  switch (status.toLowerCase()) {
    case 'draft': return 'blue'
    case 'invoiced': return 'green'
    case 'cancelled': return 'red'
    default: return 'gray'
  }
}

const getStatusLabel = (status: string): string => {
  switch (status.toLowerCase()) {
    case 'draft': return 'Draft'
    case 'invoiced': return 'Invoiced'
    case 'cancelled': return 'Dibatalkan'
    default: return status
  }
}

export default function BillingPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  // Filters
  const [filterOwnership, setFilterOwnership] = useState('')
  const [filterType, setFilterType] = useState('')
  const [filterStatus, setFilterStatus] = useState('')
  const [searchQuery, setSearchQuery] = useState('')

  // Modals
  const [modal, setModal] = useState<{ open: boolean; editing: BillingItem | null }>({
    open: false,
    editing: null,
  })
  const [detailTarget, setDetailTarget] = useState<BillingItem | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<BillingItem | null>(null)

  // ─── Generate IPL Modal State ───────────────────────────────────────────────
  const [iplModal, setIplModal] = useState(false)
  const [iplForm, setIplForm] = useState<IplGenForm>(emptyIplForm)
  const [iplErrors, setIplErrors] = useState<Record<string, string>>({})
  const [iplServerError, setIplServerError] = useState<string | null>(null)
  const [iplSuccess, setIplSuccess] = useState<BillingItem | null>(null)

  // ─── Generate Water Modal State ─────────────────────────────────────────────
  const [waterModal, setWaterModal] = useState(false)
  const [waterForm, setWaterForm] = useState<WaterGenForm>(emptyWaterForm)
  const [waterErrors, setWaterErrors] = useState<Record<string, string>>({})
  const [waterServerError, setWaterServerError] = useState<string | null>(null)
  const [waterSuccess, setWaterSuccess] = useState<BillingItem | null>(null)

  // Form State
  const [form, setForm] = useState<FormState>(emptyForm)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [serverError, setServerError] = useState<string | null>(null)

  // ─── Queries ────────────────────────────────────────────────────────────────

  const { data: billingItems, isLoading } = useQuery<BillingItem[]>({
    queryKey: ['billing-items', filterOwnership, filterType, filterStatus, searchQuery],
    queryFn: async () => {
      const params: Record<string, string> = {}
      if (filterOwnership) params.ownership_id = filterOwnership
      if (filterType) params.billing_type = filterType
      if (filterStatus) params.status = filterStatus
      if (searchQuery) params.search = searchQuery
      const res = await billingApi.list(params)
      return res.data.data
    },
  })

  const { data: ownerships } = useQuery<OwnershipOption[]>({
    queryKey: ['ownerships-all'],
    queryFn: async () => {
      const res = await ownershipApi.list()
      return res.data.data
    },
  })

  // Selected ownership helper
  const selectedOwnership = ownerships?.find((o) => String(o.id) === form.ownership_id)

  // ─── Mutations ──────────────────────────────────────────────────────────────

  const saveMutation = useMutation({
    mutationFn: async (payload: BillingItemPayload) => {
      if (modal.editing) {
        return billingApi.update(modal.editing.id, payload)
      }
      return billingApi.create(payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['billing-items'] })
      showToast(
        modal.editing ? 'Item tagihan berhasil diperbarui.' : 'Item tagihan berhasil dibuat.',
        'success'
      )
      closeModal()
    },
    onError: (err: unknown) => {
      const errorObj = err as { response?: { data?: { message?: string; errors?: Record<string, string> } } }
      const msg = errorObj.response?.data?.message || 'Gagal menyimpan item tagihan.'
      setServerError(msg)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      return billingApi.delete(id)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['billing-items'] })
      showToast('Item tagihan berhasil dihapus/dibatalkan.', 'success')
      setDeleteTarget(null)
    },
    onError: (err: unknown) => {
      const errorObj = err as { response?: { data?: { message?: string } } }
      showToast(errorObj.response?.data?.message || 'Gagal menghapus item tagihan.', 'error')
    },
  })

  // ─── Generate Water Mutation ────────────────────────────────────────────────
  const generateWaterMutation = useMutation({
    mutationFn: async () => {
      return billingApi.generateWater({
        ownership_id: Number(waterForm.ownership_id),
        billing_period_start: waterForm.billing_period_start,
        billing_period_end: waterForm.billing_period_end,
        notes: waterForm.notes.trim() || null,
      })
    },
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['billing-items'] })
      setWaterSuccess(res.data.data as BillingItem)
      setWaterServerError(null)
    },
    onError: (err: unknown) => {
      const errorObj = err as { response?: { data?: { message?: string } } }
      setWaterServerError(errorObj.response?.data?.message || 'Gagal melakukan generate tagihan Air.')
    },
  })

  // ─── Generate IPL Mutation ───────────────────────────────────────────────────
  const generateIplMutation = useMutation({
    mutationFn: async () => {
      return billingApi.generateIpl({
        ownership_id: Number(iplForm.ownership_id),
        billing_period_start: iplForm.billing_period_start,
        billing_period_end: iplForm.billing_period_end,
        notes: iplForm.notes.trim() || null,
      })
    },
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['billing-items'] })
      setIplSuccess(res.data.data as BillingItem)
      setIplServerError(null)
    },
    onError: (err: unknown) => {
      const errorObj = err as { response?: { data?: { message?: string } } }
      setIplServerError(errorObj.response?.data?.message || 'Gagal melakukan generate tagihan IPL.')
    },
  })

  // ─── Generate Water Handlers ─────────────────────────────────────────────────

  const openWaterModal = () => {
    setWaterForm(emptyWaterForm)
    setWaterErrors({})
    setWaterServerError(null)
    setWaterSuccess(null)
    setWaterModal(true)
  }

  const closeWaterModal = () => {
    setWaterModal(false)
    setWaterForm(emptyWaterForm)
    setWaterErrors({})
    setWaterServerError(null)
    setWaterSuccess(null)
  }

  // Ownerships eligible for water billing (must have a water_rate_group_id)
  const waterEligibleOwnerships = (ownerships || []).filter(
    (o) => o.water_rate_group_id != null
  )

  const selectedWaterOwnership = waterEligibleOwnerships.find(
    (o) => String(o.id) === waterForm.ownership_id
  )

  const handleWaterGenerate = (e: React.FormEvent) => {
    e.preventDefault()
    setWaterServerError(null)
    const errs: Record<string, string> = {}

    if (!waterForm.ownership_id) errs.ownership_id = 'Kepemilikan wajib dipilih.'
    if (!waterForm.billing_period_start) errs.billing_period_start = 'Tanggal awal periode wajib diisi.'
    if (!waterForm.billing_period_end) errs.billing_period_end = 'Tanggal akhir periode wajib diisi.'
    if (
      waterForm.billing_period_start &&
      waterForm.billing_period_end &&
      waterForm.billing_period_end <= waterForm.billing_period_start
    ) {
      errs.billing_period_end = 'Tanggal akhir harus lebih besar dari tanggal awal.'
    }

    if (Object.keys(errs).length > 0) {
      setWaterErrors(errs)
      return
    }
    setWaterErrors({})
    generateWaterMutation.mutate()
  }

  // ─── Generate IPL Handlers ───────────────────────────────────────────────────

  const openIplModal = () => {
    setIplForm(emptyIplForm)
    setIplErrors({})
    setIplServerError(null)
    setIplSuccess(null)
    setIplModal(true)
  }

  const closeIplModal = () => {
    setIplModal(false)
    setIplForm(emptyIplForm)
    setIplErrors({})
    setIplServerError(null)
    setIplSuccess(null)
  }

  // Ownership selected in the IPL generate modal
  const selectedIplOwnership = ownerships?.find((o) => String(o.id) === iplForm.ownership_id)

  // Preview subtotal — display only, NOT the source of truth
  const iplPreviewSubtotal =
    selectedIplOwnership && selectedIplOwnership.ipl_rate_per_sqm && selectedIplOwnership.area
      ? Number(selectedIplOwnership.area) * Number(selectedIplOwnership.ipl_rate_per_sqm)
      : null

  const handleIplGenerate = (e: React.FormEvent) => {
    e.preventDefault()
    setIplServerError(null)
    const errs: Record<string, string> = {}

    if (!iplForm.ownership_id) errs.ownership_id = 'Kepemilikan wajib dipilih.'
    if (!iplForm.billing_period_start) errs.billing_period_start = 'Tanggal awal periode wajib diisi.'
    if (!iplForm.billing_period_end) errs.billing_period_end = 'Tanggal akhir periode wajib diisi.'
    if (
      iplForm.billing_period_start &&
      iplForm.billing_period_end &&
      iplForm.billing_period_end <= iplForm.billing_period_start
    ) {
      errs.billing_period_end = 'Tanggal akhir harus lebih besar dari tanggal awal.'
    }
    if (
      iplForm.ownership_id &&
      selectedIplOwnership &&
      !selectedIplOwnership.ipl_rate_per_sqm
    ) {
      errs.ownership_id = 'Kepemilikan ini belum memiliki tarif IPL yang dikonfigurasi.'
    }

    if (Object.keys(errs).length > 0) {
      setIplErrors(errs)
      return
    }
    setIplErrors({})
    generateIplMutation.mutate()
  }

  // ─── Modal & Form Handlers ──────────────────────────────────────────────────

  const openCreate = () => {
    setForm(emptyForm)
    setErrors({})
    setServerError(null)
    setModal({ open: true, editing: null })
  }

  const openEdit = (item: BillingItem) => {
    setForm({
      ownership_id: String(item.ownership_id),
      billing_type: item.billing_type,
      billing_period_start: item.billing_period_start,
      billing_period_end: item.billing_period_end,
      description: item.description,
      quantity: String(item.quantity),
      unit: item.unit,
      unit_price: String(item.unit_price),
      apply_tax: Boolean(item.apply_tax),
      notes: item.notes || '',
    })
    setErrors({})
    setServerError(null)
    setModal({ open: true, editing: item })
  }

  const closeModal = () => {
    setModal({ open: false, editing: null })
    setForm(emptyForm)
    setErrors({})
    setServerError(null)
  }

  const handleOwnershipChange = (ownershipId: string) => {
    const found = ownerships?.find((o) => String(o.id) === ownershipId)
    setForm((prev) => {
      let defaultDesc = prev.description
      let defaultUnit = prev.unit
      let defaultQty = prev.quantity
      let defaultPrice = prev.unit_price

      if (found) {
        if (prev.billing_type === 'ipl') {
          defaultUnit = 'm²'
          defaultQty = String(found.area || '1')
          defaultPrice = String(found.ipl_rate_per_sqm || '0')
          defaultDesc = `IPL Periode ${prev.billing_period_start || ''}`
        }
      }

      return {
        ...prev,
        ownership_id: ownershipId,
        description: defaultDesc,
        unit: defaultUnit,
        quantity: defaultQty,
        unit_price: defaultPrice,
      }
    })
  }

  const handleTypeChange = (type: 'ipl' | 'water' | 'electricity' | 'other') => {
    setForm((prev) => {
      let defaultUnit = 'ls'
      let defaultQty = prev.quantity
      let defaultPrice = prev.unit_price

      if (type === 'ipl') {
        defaultUnit = 'm²'
        if (selectedOwnership) {
          defaultQty = String(selectedOwnership.area || '1')
          defaultPrice = String(selectedOwnership.ipl_rate_per_sqm || '0')
        }
      } else if (type === 'water') {
        defaultUnit = 'm³'
      }

      return {
        ...prev,
        billing_type: type,
        unit: defaultUnit,
        quantity: defaultQty,
        unit_price: defaultPrice,
      }
    })
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setServerError(null)
    const errs: Record<string, string> = {}

    if (!form.ownership_id) errs.ownership_id = 'Kepemilikan wajib dipilih.'
    if (!form.billing_type) errs.billing_type = 'Jenis tagihan wajib dipilih.'
    if (!form.billing_period_start) errs.billing_period_start = 'Tanggal awal periode wajib diisi.'
    if (!form.billing_period_end) errs.billing_period_end = 'Tanggal akhir periode wajib diisi.'
    if (form.billing_period_start && form.billing_period_end && form.billing_period_end <= form.billing_period_start) {
      errs.billing_period_end = 'Tanggal akhir periode harus lebih besar dari tanggal awal.'
    }
    if (!form.description.trim()) errs.description = 'Deskripsi tagihan wajib diisi.'
    if (isNaN(Number(form.quantity)) || Number(form.quantity) < 0) {
      errs.quantity = 'Kuantitas harus berupa angka positif.'
    }
    if (isNaN(Number(form.unit_price)) || Number(form.unit_price) < 0) {
      errs.unit_price = 'Harga satuan harus berupa angka non-negatif.'
    }

    if (Object.keys(errs).length > 0) {
      setErrors(errs)
      return
    }

    saveMutation.mutate({
      ownership_id: Number(form.ownership_id),
      billing_type: form.billing_type,
      billing_period_start: form.billing_period_start,
      billing_period_end: form.billing_period_end,
      description: form.description.trim(),
      quantity: Number(form.quantity),
      unit: form.unit.trim() || 'ls',
      unit_price: Number(form.unit_price),
      apply_tax: form.apply_tax ? 1 : 0,
      notes: form.notes.trim() || null,
    })
  }

  // Live preview subtotal
  const previewSubtotal = (Number(form.quantity) || 0) * (Number(form.unit_price) || 0)

  // ─── Summary Totals ─────────────────────────────────────────────────────────

  const totalCount = billingItems?.length || 0
  const draftSum = billingItems
    ?.filter((i) => i.status === 'draft')
    .reduce((sum, item) => sum + Number(item.subtotal), 0) || 0
  const invoicedSum = billingItems
    ?.filter((i) => i.status === 'invoiced')
    .reduce((sum, item) => sum + Number(item.subtotal), 0) || 0

  return (
    <div style={styles.container}>
      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}

      <PageHeader
        title="Item Tagihan"
        subtitle="Kelola item tagihan (IPL, Air, Listrik, Lain-lain) sebelum proses penerbitan invoice."
        onAdd={openCreate}
        addLabel="+ Tambah Manual"
      />

      {/* ─── Generate Quick Action Banners ──────────────────────────────── */}
      <div style={styles.bannerRow}>
        {/* IPL Banner */}
        <div style={styles.iplBanner}>
          <div style={styles.iplBannerLeft}>
            <div style={styles.iplBannerTitle}>⚡ Generate Tagihan IPL</div>
            <div style={styles.iplBannerDesc}>
              Hitung otomatis: Luas Area × Tarif IPL dari konfigurasi Kepemilikan.
              Backend adalah sumber kebenaran — tarif tidak bisa diubah manual.
            </div>
          </div>
          <Button
            id="btn-generate-ipl"
            variant="primary"
            onClick={openIplModal}
          >
            Generate Tagihan IPL
          </Button>
        </div>

        {/* Water Banner */}
        <div style={styles.waterBanner}>
          <div style={styles.iplBannerLeft}>
            <div style={styles.waterBannerTitle}>💧 Generate Tagihan Air</div>
            <div style={styles.iplBannerDesc}>
              Hitung otomatis: Pemakaian Air × Tarif Progresif (tier) + Abonemen.
              Backend membaca meter reading terbaru — tidak ada kalkulasi di frontend.
            </div>
          </div>
          <Button
            id="btn-generate-water"
            variant="secondary"
            onClick={openWaterModal}
          >
            Generate Tagihan Air
          </Button>
        </div>
      </div>

      {/* Summary KPI Cards */}
      <div style={styles.kpiGrid}>
        <div style={styles.kpiCard}>
          <div style={styles.kpiLabel}>Total Item Tagihan</div>
          <div style={styles.kpiValue}>{totalCount} Item</div>
          <div style={styles.kpiNote}>Semua kategori aktif</div>
        </div>
        <div style={styles.kpiCard}>
          <div style={styles.kpiLabel}>Subtotal Draft</div>
          <div style={{ ...styles.kpiValue, color: '#d97706' }}>{formatRupiah(draftSum)}</div>
          <div style={styles.kpiNote}>Siap diproses ke Invoice</div>
        </div>
        <div style={styles.kpiCard}>
          <div style={styles.kpiLabel}>Subtotal Invoiced</div>
          <div style={{ ...styles.kpiValue, color: '#059669' }}>{formatRupiah(invoicedSum)}</div>
          <div style={styles.kpiNote}>Sudah diterbitkan invoice</div>
        </div>
      </div>

      {/* Filter Bar */}
      <Card>
        <div style={styles.filterRow}>
          <div style={{ flex: '1 1 200px' }}>
            <Input
              placeholder="Cari customer, proyek, atau deskripsi..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>

          <div style={{ width: '170px' }}>
            <Select
              value={filterType}
              onChange={(e) => setFilterType(e.target.value)}
              options={[
                { value: '', label: 'Semua Jenis' },
                { value: 'ipl', label: 'IPL' },
                { value: 'water', label: 'Air' },
                { value: 'electricity', label: 'Listrik' },
                { value: 'other', label: 'Lain-lain' },
              ]}
            />
          </div>

          <div style={{ width: '160px' }}>
            <Select
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              options={[
                { value: '', label: 'Semua Status' },
                { value: 'draft', label: 'Draft' },
                { value: 'invoiced', label: 'Invoiced' },
                { value: 'cancelled', label: 'Dibatalkan' },
              ]}
            />
          </div>

          <div style={{ width: '220px' }}>
            <Select
              value={filterOwnership}
              onChange={(e) => setFilterOwnership(e.target.value)}
              options={[
                { value: '', label: 'Semua Kepemilikan' },
                ...(ownerships || []).map((o) => ({
                  value: String(o.id),
                  label: `${o.customer_name || 'Tanpa Nama'} — ${
                    o.ownership_type === 'residential'
                      ? `${o.cluster_name || ''} ${o.block_name || ''}/${o.lot_number || ''}`.trim()
                      : o.project_name || 'Komersial'
                  }`,
                })),
              ]}
            />
          </div>

          {(searchQuery || filterType || filterStatus || filterOwnership) && (
            <Button
              variant="secondary"
              onClick={() => {
                setSearchQuery('')
                setFilterType('')
                setFilterStatus('')
                setFilterOwnership('')
              }}
            >
              Reset
            </Button>
          )}
        </div>
      </Card>

      {/* Main Table */}
      <Card>
        {isLoading ? (
          <div style={styles.loadingContainer}>
            <Spinner />
            <span style={{ color: '#6b7280', fontSize: '0.875rem' }}>Memuat item tagihan...</span>
          </div>
        ) : !billingItems || billingItems.length === 0 ? (
          <EmptyState message="Belum ada item tagihan. Klik tombol '+ Tambah Item Tagihan' untuk membuat item baru." />
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table style={styles.table}>
              <thead>
                <tr>
                  <th style={styles.th}>Customer &amp; Unit</th>
                  <th style={styles.th}>Jenis</th>
                  <th style={styles.th}>Periode</th>
                  <th style={styles.th}>Deskripsi</th>
                  <th style={{ ...styles.th, textAlign: 'right' }}>Volume</th>
                  <th style={{ ...styles.th, textAlign: 'right' }}>Harga Satuan</th>
                  <th style={{ ...styles.th, textAlign: 'right' }}>Subtotal</th>
                  <th style={styles.th}>Status</th>
                  <th style={{ ...styles.th, textAlign: 'center' }}>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {billingItems.map((item) => (
                  <tr key={item.id} style={styles.tr}>
                    <td style={styles.td}>
                      <div style={styles.customerName}>{item.customer_name || 'Tanpa Nama'}</div>
                      <div style={styles.propertySub}>
                        {item.project_name}
                        {item.ownership_type === 'residential' && item.cluster_name
                          ? ` · ${item.cluster_name} ${item.block_name || ''}/${item.lot_number || ''}`.trim()
                          : ''}
                      </div>
                    </td>
                    <td style={styles.td}>
                      <Badge
                        label={getTypeLabel(item.billing_type)}
                        color={getTypeBadgeColor(item.billing_type)}
                      />
                    </td>
                    <td style={styles.td}>
                      <div style={styles.periodText}>{item.billing_period_start}</div>
                      <div style={styles.periodSub}>s/d {item.billing_period_end}</div>
                    </td>
                    <td style={styles.td}>
                      <div style={styles.descText}>{item.description}</div>
                      {item.notes && <div style={styles.notesSub}>{item.notes}</div>}
                    </td>
                    <td style={{ ...styles.td, textAlign: 'right', whiteSpace: 'nowrap' }}>
                      {formatNumber(item.quantity)} {item.unit}
                    </td>
                    <td style={{ ...styles.td, textAlign: 'right', whiteSpace: 'nowrap' }}>
                      {formatRupiah(item.unit_price)}
                    </td>
                    <td style={{ ...styles.td, textAlign: 'right', fontWeight: '600', color: '#111827', whiteSpace: 'nowrap' }}>
                      {formatRupiah(item.subtotal)}
                    </td>
                    <td style={styles.td}>
                      <Badge
                        label={getStatusLabel(item.status)}
                        color={getStatusBadgeColor(item.status)}
                      />
                    </td>
                    <td style={{ ...styles.td, textAlign: 'center', whiteSpace: 'nowrap' }}>
                      <div style={styles.actionGroup}>
                        <button
                          style={styles.actionBtn}
                          title="Lihat Detail"
                          onClick={() => setDetailTarget(item)}
                        >
                          👁
                        </button>
                        {item.status === 'draft' && (
                          <>
                            <button
                              style={styles.actionBtn}
                              title="Ubah"
                              onClick={() => openEdit(item)}
                            >
                              ✏️
                            </button>
                            <button
                              style={{ ...styles.actionBtn, color: '#dc2626' }}
                              title="Hapus / Batalkan"
                              onClick={() => setDeleteTarget(item)}
                            >
                              🗑
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* ─── Modal Form: Create / Edit ─────────────────────────────────────── */}
      <Modal
        open={modal.open}
        title={modal.editing ? 'Ubah Item Tagihan' : 'Tambah Item Tagihan'}
        onClose={closeModal}
        width={560}
      >
        <form onSubmit={handleSubmit} style={styles.form}>
          {serverError && (
            <div style={styles.errorAlert} role="alert">
              {serverError}
            </div>
          )}

          {/* Ownership Selection */}
          <div>
            <label style={styles.label}>
              Kepemilikan / Properti <span style={{ color: '#ef4444' }}>*</span>
            </label>
            <Select
              value={form.ownership_id}
              onChange={(e) => handleOwnershipChange(e.target.value)}
              options={[
                { value: '', label: '-- Pilih Kepemilikan / Customer --' },
                ...(ownerships || []).map((o) => ({
                  value: String(o.id),
                  label: `${o.customer_name || 'Tanpa Nama'} — ${
                    o.ownership_type === 'residential'
                      ? `${o.project_name || ''} (${o.cluster_name || ''} ${o.block_name || ''}/${o.lot_number || ''})`
                      : `${o.project_name || ''} (${o.billing_address || 'Komersial'})`
                  }`,
                })),
              ]}
            />
            {errors.ownership_id && <span style={styles.fieldError}>{errors.ownership_id}</span>}

            {/* Ownership context summary box */}
            {selectedOwnership && (
              <div style={styles.contextBox}>
                <div style={styles.contextRow}>
                  <span style={styles.contextLabel}>Customer:</span>
                  <span style={styles.contextValue}>{selectedOwnership.customer_name}</span>
                </div>
                <div style={styles.contextRow}>
                  <span style={styles.contextLabel}>Proyek:</span>
                  <span style={styles.contextValue}>{selectedOwnership.project_name} ({selectedOwnership.ownership_type})</span>
                </div>
                {selectedOwnership.area && (
                  <div style={styles.contextRow}>
                    <span style={styles.contextLabel}>Luas Tanah:</span>
                    <span style={styles.contextValue}>{selectedOwnership.area} m²</span>
                  </div>
                )}
                {selectedOwnership.ipl_rate_per_sqm && (
                  <div style={styles.contextRow}>
                    <span style={styles.contextLabel}>Tarif IPL:</span>
                    <span style={styles.contextValue}>{formatRupiah(selectedOwnership.ipl_rate_per_sqm)} / m²</span>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Billing Type Selector */}
          <div>
            <label style={styles.label}>
              Jenis Tagihan <span style={{ color: '#ef4444' }}>*</span>
            </label>
            <div style={styles.typeSelectorGroup}>
              {(['ipl', 'water', 'electricity', 'other'] as const).map((t) => (
                <button
                  key={t}
                  type="button"
                  onClick={() => handleTypeChange(t)}
                  style={{
                    ...styles.typeBtn,
                    ...(form.billing_type === t ? styles.typeBtnActive : {}),
                  }}
                >
                  {getTypeLabel(t)}
                </button>
              ))}
            </div>
            {errors.billing_type && <span style={styles.fieldError}>{errors.billing_type}</span>}
          </div>

          {/* Billing Period Dates */}
          <div style={styles.rowTwo}>
            <div>
              <label style={styles.label}>
                Tanggal Awal Periode <span style={{ color: '#ef4444' }}>*</span>
              </label>
              <Input
                type="date"
                value={form.billing_period_start}
                onChange={(e) => setForm({ ...form, billing_period_start: e.target.value })}
              />
              {errors.billing_period_start && <span style={styles.fieldError}>{errors.billing_period_start}</span>}
            </div>

            <div>
              <label style={styles.label}>
                Tanggal Akhir Periode <span style={{ color: '#ef4444' }}>*</span>
              </label>
              <Input
                type="date"
                value={form.billing_period_end}
                onChange={(e) => setForm({ ...form, billing_period_end: e.target.value })}
              />
              {errors.billing_period_end && <span style={styles.fieldError}>{errors.billing_period_end}</span>}
            </div>
          </div>

          {/* Description */}
          <div>
            <label style={styles.label}>
              Deskripsi Item Tagihan <span style={{ color: '#ef4444' }}>*</span>
            </label>
            <Input
              placeholder="Contoh: Iuran Pengelolaan Lingkungan (IPL) Juli 2026"
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
            />
            {errors.description && <span style={styles.fieldError}>{errors.description}</span>}
          </div>

          {/* Quantity, Unit, Unit Price */}
          <div style={styles.rowThree}>
            <div>
              <label style={styles.label}>
                Kuantitas <span style={{ color: '#ef4444' }}>*</span>
              </label>
              <Input
                type="number"
                step="any"
                min="0"
                value={form.quantity}
                onChange={(e) => setForm({ ...form, quantity: e.target.value })}
              />
              {errors.quantity && <span style={styles.fieldError}>{errors.quantity}</span>}
            </div>

            <div>
              <label style={styles.label}>Satuan</label>
              <Input
                placeholder="m², m³, ls, unit"
                value={form.unit}
                onChange={(e) => setForm({ ...form, unit: e.target.value })}
              />
            </div>

            <div>
              <label style={styles.label}>
                Harga Satuan (Rp) <span style={{ color: '#ef4444' }}>*</span>
              </label>
              <Input
                type="number"
                step="any"
                min="0"
                value={form.unit_price}
                onChange={(e) => setForm({ ...form, unit_price: e.target.value })}
              />
              {errors.unit_price && <span style={styles.fieldError}>{errors.unit_price}</span>}
            </div>
          </div>

          {/* Subtotal Preview */}
          <div style={styles.subtotalPreviewBox}>
            <div style={styles.subtotalPreviewLabel}>Preview Perhitungan Subtotal:</div>
            <div style={styles.subtotalPreviewFormula}>
              {formatNumber(form.quantity)} {form.unit || 'satuan'} × {formatRupiah(form.unit_price)}
            </div>
            <div style={styles.subtotalPreviewVal}>
              = {formatRupiah(previewSubtotal)}
            </div>
            <div style={styles.subtotalNotice}>
              * Subtotal final dan authoritative dihitung dan divalidasi oleh backend.
            </div>
          </div>

          {/* Apply Tax */}
          <label style={styles.checkboxLabel}>
            <input
              type="checkbox"
              checked={form.apply_tax}
              onChange={(e) => setForm({ ...form, apply_tax: e.target.checked })}
              style={{ width: '16px', height: '16px', accentColor: '#2d5a3d' }}
            />
            <span style={{ fontSize: '0.875rem', color: '#374151', fontWeight: '500' }}>
              Termasuk objek pajak (DPP Nilai Lain / PPN pada saat penerbitan invoice)
            </span>
          </label>

          {/* Notes */}
          <div>
            <label style={styles.label}>Catatan Tambahan (Opsional)</label>
            <Textarea
              placeholder="Catatan internal untuk item tagihan ini..."
              value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
              rows={2}
            />
          </div>

          {/* Form Actions */}
          <div style={styles.modalActions}>
            <Button type="button" variant="secondary" onClick={closeModal}>
              Batal
            </Button>
            <Button type="submit" variant="primary" disabled={saveMutation.isPending}>
              {saveMutation.isPending ? 'Menyimpan...' : modal.editing ? 'Simpan Perubahan' : 'Buat Item Tagihan'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* ─── Detail Modal ─────────────────────────────────────────────────── */}
      <Modal
        open={Boolean(detailTarget)}
        title="Detail Item Tagihan"
        onClose={() => setDetailTarget(null)}
        width={560}
      >
        {detailTarget && (
          <div style={styles.detailContainer}>
            <div style={styles.detailGrid}>
              <div style={styles.detailItem}>
                <span style={styles.detailLabel}>Customer</span>
                <span style={styles.detailVal}>{detailTarget.customer_name || '-'}</span>
              </div>
              <div style={styles.detailItem}>
                <span style={styles.detailLabel}>Proyek &amp; Unit</span>
                <span style={styles.detailVal}>
                  {detailTarget.project_name}
                  {detailTarget.cluster_name ? ` · ${detailTarget.cluster_name}` : ''}
                  {detailTarget.block_name ? ` ${detailTarget.block_name}` : ''}
                  {detailTarget.lot_number ? `/${detailTarget.lot_number}` : ''}
                </span>
              </div>
              <div style={styles.detailItem}>
                <span style={styles.detailLabel}>Jenis Tagihan</span>
                <Badge
                  label={getTypeLabel(detailTarget.billing_type)}
                  color={getTypeBadgeColor(detailTarget.billing_type)}
                />
              </div>
              <div style={styles.detailItem}>
                <span style={styles.detailLabel}>Status</span>
                <Badge
                  label={getStatusLabel(detailTarget.status)}
                  color={getStatusBadgeColor(detailTarget.status)}
                />
              </div>
              <div style={styles.detailItem}>
                <span style={styles.detailLabel}>Periode Tagihan</span>
                <span style={styles.detailVal}>
                  {detailTarget.billing_period_start} s/d {detailTarget.billing_period_end}
                </span>
              </div>
              <div style={styles.detailItem}>
                <span style={styles.detailLabel}>Status Pajak</span>
                <span style={styles.detailVal}>
                  {detailTarget.apply_tax ? '✅ Termasuk objek pajak' : '❌ Non-pajak'}
                </span>
              </div>
            </div>

            <div style={styles.detailCalcCard}>
              <div style={styles.detailCalcTitle}>Deskripsi: {detailTarget.description}</div>
              <div style={styles.detailCalcRow}>
                <span>Volume / Kuantitas:</span>
                <span>{formatNumber(detailTarget.quantity)} {detailTarget.unit}</span>
              </div>
              <div style={styles.detailCalcRow}>
                <span>Harga Satuan:</span>
                <span>{formatRupiah(detailTarget.unit_price)}</span>
              </div>
              <div style={{ ...styles.detailCalcRow, ...styles.detailCalcTotal }}>
                <span>Subtotal:</span>
                <span>{formatRupiah(detailTarget.subtotal)}</span>
              </div>
            </div>

            {detailTarget.notes && (
              <div style={styles.detailNotes}>
                <span style={styles.detailLabel}>Catatan:</span>
                <p style={{ margin: '0.25rem 0 0', color: '#374151', fontSize: '0.875rem' }}>
                  {detailTarget.notes}
                </p>
              </div>
            )}

            <div style={styles.modalActions}>
              <Button variant="primary" onClick={() => setDetailTarget(null)}>
                Tutup
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ─── Generate IPL Modal ───────────────────────────────────────────── */}
      <Modal
        open={iplModal}
        title="Generate Tagihan IPL"
        onClose={closeIplModal}
        width={560}
      >
        {/* Success result screen */}
        {iplSuccess ? (
          <div style={styles.iplSuccessContainer}>
            <div style={styles.iplSuccessIcon}>✅</div>
            <div style={styles.iplSuccessTitle}>Tagihan IPL Berhasil Digenerate!</div>
            <div style={styles.iplSuccessCard}>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Customer</span>
                <span style={styles.iplSuccessVal}>{iplSuccess.customer_name || '-'}</span>
              </div>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Proyek</span>
                <span style={styles.iplSuccessVal}>{iplSuccess.project_name || '-'}</span>
              </div>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Periode</span>
                <span style={styles.iplSuccessVal}>
                  {iplSuccess.billing_period_start} s/d {iplSuccess.billing_period_end}
                </span>
              </div>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Area</span>
                <span style={styles.iplSuccessVal}>{formatNumber(iplSuccess.quantity)} m²</span>
              </div>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Tarif IPL</span>
                <span style={styles.iplSuccessVal}>{formatRupiah(iplSuccess.unit_price)} / m²</span>
              </div>
              <div style={{ ...styles.iplSuccessRow, ...styles.iplSuccessTotal }}>
                <span>Subtotal (Authoritative Backend)</span>
                <span>{formatRupiah(iplSuccess.subtotal)}</span>
              </div>
            </div>
            <div style={styles.iplSuccessDesc}>{iplSuccess.description}</div>
            <div style={styles.modalActions}>
              <Button
                variant="secondary"
                onClick={() => {
                  setIplSuccess(null)
                  setIplForm(emptyIplForm)
                }}
              >
                Generate Lagi
              </Button>
              <Button variant="primary" onClick={closeIplModal}>
                Selesai
              </Button>
            </div>
          </div>
        ) : (
          <form onSubmit={handleIplGenerate} style={styles.form}>
            {/* Server error */}
            {iplServerError && (
              <div style={styles.errorAlert} role="alert">
                {iplServerError}
              </div>
            )}

            {/* Step 1: Select Ownership */}
            <div>
              <label style={styles.label}>
                Pilih Kepemilikan <span style={{ color: '#ef4444' }}>*</span>
              </label>
              <select
                id="ipl-ownership-select"
                value={iplForm.ownership_id}
                onChange={(e) => {
                  setIplForm({ ...iplForm, ownership_id: e.target.value })
                  setIplErrors({})
                  setIplServerError(null)
                }}
                style={styles.nativeSelect}
              >
                <option value="">-- Pilih Kepemilikan / Customer --</option>
                {(ownerships || []).map((o) => (
                  <option key={o.id} value={String(o.id)}>
                    {o.customer_name || 'Tanpa Nama'} —{' '}
                    {o.ownership_type === 'residential'
                      ? `${o.project_name || ''} (${o.cluster_name || ''} ${o.block_name || ''}/${o.lot_number || ''})`
                      : `${o.project_name || ''} (${o.billing_address || 'Komersial'})`}
                  </option>
                ))}
              </select>
              {iplErrors.ownership_id && (
                <span style={styles.fieldError}>{iplErrors.ownership_id}</span>
              )}

              {/* Ownership info panel — read-only */}
              {selectedIplOwnership && (
                <div style={styles.iplInfoPanel}>
                  <div style={styles.iplInfoPanelTitle}>Informasi Kepemilikan</div>
                  <div style={styles.iplInfoRow}>
                    <span style={styles.iplInfoLabel}>Customer</span>
                    <span style={styles.iplInfoVal}>{selectedIplOwnership.customer_name || '-'}</span>
                  </div>
                  <div style={styles.iplInfoRow}>
                    <span style={styles.iplInfoLabel}>Proyek</span>
                    <span style={styles.iplInfoVal}>
                      {selectedIplOwnership.project_name || '-'}
                      {selectedIplOwnership.ownership_type === 'residential' &&
                        selectedIplOwnership.cluster_name
                        ? ` · ${selectedIplOwnership.cluster_name} ${selectedIplOwnership.block_name || ''}/${selectedIplOwnership.lot_number || ''}`
                        : ''}
                    </span>
                  </div>
                  <div style={styles.iplInfoRow}>
                    <span style={styles.iplInfoLabel}>Luas Area</span>
                    <span style={styles.iplInfoVal}>
                      {selectedIplOwnership.area ? `${formatNumber(selectedIplOwnership.area)} m²` : '—'}
                    </span>
                  </div>
                  <div style={styles.iplInfoRow}>
                    <span style={styles.iplInfoLabel}>Tarif IPL</span>
                    <span style={styles.iplInfoVal}>
                      {selectedIplOwnership.ipl_rate_per_sqm
                        ? `${formatRupiah(selectedIplOwnership.ipl_rate_per_sqm)} / m²`
                        : <span style={{ color: '#dc2626' }}>⚠️ Belum dikonfigurasi</span>
                      }
                    </span>
                  </div>
                  {selectedIplOwnership.ipl_rate_name && (
                    <div style={styles.iplInfoRow}>
                      <span style={styles.iplInfoLabel}>Nama Tarif</span>
                      <span style={styles.iplInfoVal}>{selectedIplOwnership.ipl_rate_name}</span>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Step 2: Billing Period */}
            <div style={styles.rowTwo}>
              <div>
                <label style={styles.label}>
                  Tanggal Awal Periode <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <Input
                  id="ipl-period-start"
                  type="date"
                  value={iplForm.billing_period_start}
                  onChange={(e) => setIplForm({ ...iplForm, billing_period_start: e.target.value })}
                />
                {iplErrors.billing_period_start && (
                  <span style={styles.fieldError}>{iplErrors.billing_period_start}</span>
                )}
              </div>
              <div>
                <label style={styles.label}>
                  Tanggal Akhir Periode <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <Input
                  id="ipl-period-end"
                  type="date"
                  value={iplForm.billing_period_end}
                  onChange={(e) => setIplForm({ ...iplForm, billing_period_end: e.target.value })}
                />
                {iplErrors.billing_period_end && (
                  <span style={styles.fieldError}>{iplErrors.billing_period_end}</span>
                )}
              </div>
            </div>

            {/* Step 3: Preview (display only) */}
            {selectedIplOwnership && iplPreviewSubtotal !== null && (
              <div style={styles.iplPreviewBox}>
                <div style={styles.iplPreviewTitle}>Preview Perhitungan (Tampilan Saja)</div>
                <div style={styles.iplPreviewFormula}>
                  {formatNumber(selectedIplOwnership.area)} m² ×{' '}
                  {formatRupiah(selectedIplOwnership.ipl_rate_per_sqm)} / m²
                </div>
                <div style={styles.iplPreviewResult}>
                  = {formatRupiah(iplPreviewSubtotal)}
                </div>
                <div style={styles.iplPreviewNotice}>
                  * Subtotal final dan authoritative dihitung dan divalidasi oleh backend.
                  Area dan tarif hanya dapat diubah melalui konfigurasi Kepemilikan.
                </div>
              </div>
            )}

            {/* Optional notes */}
            <div>
              <label style={styles.label}>Catatan Tambahan (Opsional)</label>
              <Textarea
                placeholder="Catatan internal untuk tagihan IPL ini..."
                value={iplForm.notes}
                onChange={(e) => setIplForm({ ...iplForm, notes: e.target.value })}
                rows={2}
              />
            </div>

            <div style={styles.modalActions}>
              <Button type="button" variant="secondary" onClick={closeIplModal}>
                Batal
              </Button>
              <Button
                id="btn-submit-generate-ipl"
                type="submit"
                variant="primary"
                disabled={generateIplMutation.isPending}
              >
                {generateIplMutation.isPending ? 'Memproses...' : 'Generate Tagihan IPL'}
              </Button>
            </div>
          </form>
        )}
      </Modal>

      {/* ─── Generate Water Modal ──────────────────────────────────────────── */}
      <Modal
        open={waterModal}
        title="Generate Tagihan Air"
        onClose={closeWaterModal}
        width={560}
      >
        {/* Success result screen */}
        {waterSuccess ? (
          <div style={styles.iplSuccessContainer}>
            <div style={styles.iplSuccessIcon}>✅</div>
            <div style={styles.iplSuccessTitle}>Tagihan Air Berhasil Digenerate!</div>
            <div style={styles.iplSuccessCard}>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Customer</span>
                <span style={styles.iplSuccessVal}>{waterSuccess.customer_name || '-'}</span>
              </div>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Proyek</span>
                <span style={styles.iplSuccessVal}>{waterSuccess.project_name || '-'}</span>
              </div>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Periode</span>
                <span style={styles.iplSuccessVal}>
                  {waterSuccess.billing_period_start} s/d {waterSuccess.billing_period_end}
                </span>
              </div>
              <div style={styles.iplSuccessRow}>
                <span style={styles.iplSuccessLabel}>Pemakaian Air</span>
                <span style={styles.iplSuccessVal}>{formatNumber(waterSuccess.quantity)} m³</span>
              </div>
              <div style={{ ...styles.iplSuccessRow, ...styles.iplSuccessTotal }}>
                <span>Subtotal (Authoritative Backend)</span>
                <span>{formatRupiah(waterSuccess.subtotal)}</span>
              </div>
            </div>
            {waterSuccess.notes && (
              <div style={styles.iplSuccessDesc}>
                <span style={{ fontSize: '0.75rem', color: '#6b7280' }}>Catatan:</span>{' '}
                {waterSuccess.notes}
              </div>
            )}
            <div style={styles.modalActions}>
              <Button
                variant="secondary"
                onClick={() => {
                  setWaterSuccess(null)
                  setWaterForm(emptyWaterForm)
                }}
              >
                Generate Lagi
              </Button>
              <Button variant="primary" onClick={closeWaterModal}>
                Selesai
              </Button>
            </div>
          </div>
        ) : (
          <form onSubmit={handleWaterGenerate} style={styles.form}>
            {/* Server error */}
            {waterServerError && (
              <div style={styles.errorAlert} role="alert">
                {waterServerError}
              </div>
            )}

            {/* Step 1: Select Ownership (water-eligible only) */}
            <div>
              <label style={styles.label}>
                Pilih Kepemilikan <span style={{ color: '#ef4444' }}>*</span>
              </label>
              {waterEligibleOwnerships.length === 0 ? (
                <div style={styles.waterNoOwnershipAlert}>
                  ⚠️ Tidak ada kepemilikan dengan konfigurasi Kelompok Tarif Air aktif.
                  Pastikan kepemilikan sudah memiliki{' '}
                  <strong>water_rate_group_id</strong> yang dikonfigurasi.
                </div>
              ) : (
                <select
                  id="water-ownership-select"
                  value={waterForm.ownership_id}
                  onChange={(e) => {
                    setWaterForm({ ...waterForm, ownership_id: e.target.value })
                    setWaterErrors({})
                    setWaterServerError(null)
                  }}
                  style={styles.nativeSelect}
                >
                  <option value="">-- Pilih Kepemilikan dengan Tarif Air --</option>
                  {waterEligibleOwnerships.map((o) => (
                    <option key={o.id} value={String(o.id)}>
                      {o.customer_name || 'Tanpa Nama'} —{' '}
                      {o.ownership_type === 'residential'
                        ? `${o.project_name || ''} (${o.cluster_name || ''} ${o.block_name || ''}/${o.lot_number || ''})`
                        : `${o.project_name || ''} (${o.billing_address || 'Komersial'})`}
                    </option>
                  ))}
                </select>
              )}
              {waterErrors.ownership_id && (
                <span style={styles.fieldError}>{waterErrors.ownership_id}</span>
              )}

              {/* Ownership water info panel — read-only, display only */}
              {selectedWaterOwnership && (
                <div style={styles.waterInfoPanel}>
                  <div style={styles.iplInfoPanelTitle}>Informasi Kepemilikan</div>
                  <div style={styles.iplInfoRow}>
                    <span style={styles.iplInfoLabel}>Customer</span>
                    <span style={styles.iplInfoVal}>{selectedWaterOwnership.customer_name || '-'}</span>
                  </div>
                  <div style={styles.iplInfoRow}>
                    <span style={styles.iplInfoLabel}>Proyek</span>
                    <span style={styles.iplInfoVal}>
                      {selectedWaterOwnership.project_name || '-'}
                      {selectedWaterOwnership.ownership_type === 'residential' &&
                        selectedWaterOwnership.cluster_name
                        ? ` · ${selectedWaterOwnership.cluster_name} ${selectedWaterOwnership.block_name || ''}/${selectedWaterOwnership.lot_number || ''}`
                        : ''}
                    </span>
                  </div>
                  <div style={styles.iplInfoRow}>
                    <span style={styles.iplInfoLabel}>Kelompok Tarif Air</span>
                    <span style={styles.iplInfoVal}>
                      {selectedWaterOwnership.water_rate_group_name
                        ? <strong>{selectedWaterOwnership.water_rate_group_name}</strong>
                        : <span style={{ color: '#dc2626' }}>⚠️ Belum dikonfigurasi</span>
                      }
                    </span>
                  </div>
                  <div style={styles.waterInfoNotice}>
                    💡 Backend akan membaca meter reading terbaru untuk kepemilikan ini
                    dan menghitung tarif progresif secara otomatis.
                    Tidak ada kalkulasi yang dilakukan di frontend.
                  </div>
                </div>
              )}
            </div>

            {/* Step 2: Billing Period */}
            <div style={styles.rowTwo}>
              <div>
                <label style={styles.label}>
                  Tanggal Awal Periode <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <Input
                  id="water-period-start"
                  type="date"
                  value={waterForm.billing_period_start}
                  onChange={(e) => setWaterForm({ ...waterForm, billing_period_start: e.target.value })}
                />
                {waterErrors.billing_period_start && (
                  <span style={styles.fieldError}>{waterErrors.billing_period_start}</span>
                )}
              </div>
              <div>
                <label style={styles.label}>
                  Tanggal Akhir Periode <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <Input
                  id="water-period-end"
                  type="date"
                  value={waterForm.billing_period_end}
                  onChange={(e) => setWaterForm({ ...waterForm, billing_period_end: e.target.value })}
                />
                {waterErrors.billing_period_end && (
                  <span style={styles.fieldError}>{waterErrors.billing_period_end}</span>
                )}
              </div>
            </div>

            {/* Optional notes */}
            <div>
              <label style={styles.label}>Catatan Tambahan (Opsional)</label>
              <Textarea
                placeholder="Catatan internal untuk tagihan Air ini..."
                value={waterForm.notes}
                onChange={(e) => setWaterForm({ ...waterForm, notes: e.target.value })}
                rows={2}
              />
            </div>

            <div style={styles.modalActions}>
              <Button type="button" variant="secondary" onClick={closeWaterModal}>
                Batal
              </Button>
              <Button
                id="btn-submit-generate-water"
                type="submit"
                variant="primary"
                disabled={generateWaterMutation.isPending || waterEligibleOwnerships.length === 0}
              >
                {generateWaterMutation.isPending ? 'Memproses...' : 'Generate Tagihan Air'}
              </Button>
            </div>
          </form>
        )}
      </Modal>

      {/* ─── Delete Confirmation Dialog ───────────────────────────────────── */}
      <ConfirmDialog
        open={Boolean(deleteTarget)}
        title="Hapus / Batalkan Item Tagihan"
        message={`Apakah Anda yakin ingin membatalkan/menghapus item tagihan "${deleteTarget?.description}"? Catatan ini akan di-soft delete dan riwayatnya tetap tersimpan.`}
        loading={deleteMutation.isPending}
        onConfirm={() => {
          if (deleteTarget) deleteMutation.mutate(deleteTarget.id)
        }}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  )
}

const styles: Record<string, React.CSSProperties> = {
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.25rem',
  },
  kpiGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
    gap: '1rem',
  },
  kpiCard: {
    backgroundColor: '#ffffff',
    borderRadius: '8px',
    border: '1px solid #e5e7eb',
    padding: '1.25rem',
    boxShadow: '0 1px 2px rgba(0,0,0,0.04)',
  },
  kpiLabel: {
    fontSize: '0.75rem',
    color: '#6b7280',
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
    marginBottom: '0.375rem',
  },
  kpiValue: {
    fontSize: '1.25rem',
    fontWeight: '700',
    color: '#111827',
    marginBottom: '0.25rem',
  },
  kpiNote: {
    fontSize: '0.8125rem',
    color: '#9ca3af',
  },
  filterRow: {
    display: 'flex',
    flexWrap: 'wrap',
    gap: '0.75rem',
    alignItems: 'center',
  },
  loadingContainer: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: '0.75rem',
    padding: '3rem',
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
    fontSize: '0.875rem',
    textAlign: 'left',
  },
  th: {
    padding: '0.75rem 1rem',
    backgroundColor: '#f9fafb',
    color: '#4b5563',
    fontWeight: '600',
    borderBottom: '1px solid #e5e7eb',
    fontSize: '0.8125rem',
    textTransform: 'uppercase',
    letterSpacing: '0.03em',
  },
  tr: {
    borderBottom: '1px solid #f3f4f6',
    transition: 'background-color 0.15s',
  },
  td: {
    padding: '0.875rem 1rem',
    verticalAlign: 'middle',
  },
  customerName: {
    fontWeight: '600',
    color: '#111827',
  },
  propertySub: {
    fontSize: '0.75rem',
    color: '#6b7280',
    marginTop: '0.125rem',
  },
  periodText: {
    fontWeight: '500',
    color: '#374151',
    fontSize: '0.8125rem',
  },
  periodSub: {
    fontSize: '0.75rem',
    color: '#6b7280',
  },
  descText: {
    color: '#1f2937',
    fontWeight: '500',
    maxWidth: '260px',
  },
  notesSub: {
    fontSize: '0.75rem',
    color: '#9ca3af',
    marginTop: '0.125rem',
    fontStyle: 'italic',
  },
  actionGroup: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.375rem',
  },
  actionBtn: {
    background: 'none',
    border: '1px solid #e5e7eb',
    borderRadius: '4px',
    padding: '0.25rem 0.5rem',
    cursor: 'pointer',
    fontSize: '0.875rem',
    transition: 'background-color 0.15s',
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.125rem',
  },
  errorAlert: {
    backgroundColor: '#fef2f2',
    border: '1px solid #fecaca',
    color: '#b91c1c',
    borderRadius: '6px',
    padding: '0.75rem 1rem',
    fontSize: '0.875rem',
  },
  label: {
    display: 'block',
    fontSize: '0.8125rem',
    fontWeight: '600',
    color: '#374151',
    marginBottom: '0.375rem',
  },
  fieldError: {
    display: 'block',
    fontSize: '0.75rem',
    color: '#ef4444',
    marginTop: '0.25rem',
  },
  contextBox: {
    backgroundColor: '#f8fafc',
    border: '1px solid #e2e8f0',
    borderRadius: '6px',
    padding: '0.625rem 0.875rem',
    marginTop: '0.5rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.25rem',
  },
  contextRow: {
    display: 'flex',
    justifyContent: 'space-between',
    fontSize: '0.8125rem',
  },
  contextLabel: {
    color: '#64748b',
  },
  contextValue: {
    color: '#1e293b',
    fontWeight: '500',
  },
  typeSelectorGroup: {
    display: 'grid',
    gridTemplateColumns: 'repeat(4, 1fr)',
    gap: '0.5rem',
  },
  typeBtn: {
    padding: '0.5rem',
    border: '1px solid #d1d5db',
    borderRadius: '6px',
    backgroundColor: '#ffffff',
    color: '#4b5563',
    fontWeight: '600',
    fontSize: '0.8125rem',
    cursor: 'pointer',
    transition: 'all 0.15s',
  },
  typeBtnActive: {
    backgroundColor: '#2d5a3d',
    borderColor: '#2d5a3d',
    color: '#ffffff',
  },
  rowTwo: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: '1rem',
  },
  rowThree: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr 1.25fr',
    gap: '0.75rem',
  },
  subtotalPreviewBox: {
    backgroundColor: '#f0fdf4',
    border: '1px solid #bbf7d0',
    borderRadius: '6px',
    padding: '0.875rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.25rem',
  },
  subtotalPreviewLabel: {
    fontSize: '0.75rem',
    fontWeight: '600',
    color: '#166534',
    textTransform: 'uppercase',
    letterSpacing: '0.03em',
  },
  subtotalPreviewFormula: {
    fontSize: '0.8125rem',
    color: '#374151',
  },
  subtotalPreviewVal: {
    fontSize: '1.125rem',
    fontWeight: '700',
    color: '#15803d',
  },
  subtotalNotice: {
    fontSize: '0.6875rem',
    color: '#6b7280',
    marginTop: '0.125rem',
    fontStyle: 'italic',
  },
  checkboxLabel: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.5rem',
    cursor: 'pointer',
    userSelect: 'none',
  },
  modalActions: {
    display: 'flex',
    justifyContent: 'flex-end',
    gap: '0.75rem',
    marginTop: '0.75rem',
  },
  detailContainer: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1.25rem',
  },
  detailGrid: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: '1rem',
  },
  detailItem: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.25rem',
  },
  detailLabel: {
    fontSize: '0.75rem',
    fontWeight: '600',
    color: '#6b7280',
    textTransform: 'uppercase',
    letterSpacing: '0.03em',
  },
  detailVal: {
    fontSize: '0.9375rem',
    fontWeight: '500',
    color: '#111827',
  },
  detailCalcCard: {
    backgroundColor: '#f8fafc',
    border: '1px solid #e2e8f0',
    borderRadius: '6px',
    padding: '1rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  detailCalcTitle: {
    fontSize: '0.875rem',
    fontWeight: '600',
    color: '#1e293b',
    marginBottom: '0.25rem',
  },
  detailCalcRow: {
    display: 'flex',
    justifyContent: 'space-between',
    fontSize: '0.8125rem',
    color: '#475569',
  },
  detailCalcTotal: {
    borderTop: '1px solid #cbd5e1',
    paddingTop: '0.5rem',
    marginTop: '0.25rem',
    fontSize: '0.9375rem',
    fontWeight: '700',
    color: '#0f172a',
  },
  detailNotes: {
    backgroundColor: '#f9fafb',
    border: '1px solid #e5e7eb',
    borderRadius: '6px',
    padding: '0.75rem',
  },

  // ─── Generate IPL styles ────────────────────────────────────────────────────
  iplBanner: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: '1rem',
    backgroundColor: '#f0fdf4',
    border: '1px solid #86efac',
    borderRadius: '8px',
    padding: '1rem 1.25rem',
  },
  iplBannerLeft: {
    display: 'flex',
    flexDirection: 'column' as const,
    gap: '0.25rem',
  },
  iplBannerTitle: {
    fontSize: '0.9375rem',
    fontWeight: '700',
    color: '#15803d',
  },
  iplBannerDesc: {
    fontSize: '0.8125rem',
    color: '#166534',
    opacity: 0.8,
  },
  iplInfoPanel: {
    marginTop: '0.625rem',
    backgroundColor: '#f8fafc',
    border: '1px solid #cbd5e1',
    borderRadius: '6px',
    padding: '0.75rem 1rem',
    display: 'flex',
    flexDirection: 'column' as const,
    gap: '0.375rem',
  },
  iplInfoPanelTitle: {
    fontSize: '0.75rem',
    fontWeight: '700',
    color: '#475569',
    textTransform: 'uppercase' as const,
    letterSpacing: '0.04em',
    marginBottom: '0.25rem',
  },
  iplInfoRow: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    fontSize: '0.8125rem',
    gap: '0.5rem',
  },
  iplInfoLabel: {
    color: '#64748b',
    minWidth: '90px',
  },
  iplInfoVal: {
    color: '#1e293b',
    fontWeight: '600',
    textAlign: 'right' as const,
  },
  iplPreviewBox: {
    backgroundColor: '#eff6ff',
    border: '1px solid #bfdbfe',
    borderRadius: '6px',
    padding: '0.875rem',
    display: 'flex',
    flexDirection: 'column' as const,
    gap: '0.25rem',
  },
  iplPreviewTitle: {
    fontSize: '0.75rem',
    fontWeight: '700',
    color: '#1d4ed8',
    textTransform: 'uppercase' as const,
    letterSpacing: '0.03em',
  },
  iplPreviewFormula: {
    fontSize: '0.875rem',
    color: '#374151',
    marginTop: '0.125rem',
  },
  iplPreviewResult: {
    fontSize: '1.125rem',
    fontWeight: '700',
    color: '#1d4ed8',
  },
  iplPreviewNotice: {
    fontSize: '0.6875rem',
    color: '#6b7280',
    fontStyle: 'italic',
    marginTop: '0.125rem',
  },
  iplSuccessContainer: {
    display: 'flex',
    flexDirection: 'column' as const,
    alignItems: 'center',
    gap: '0.875rem',
    paddingBottom: '0.5rem',
  },
  iplSuccessIcon: {
    fontSize: '2.5rem',
    lineHeight: 1,
  },
  iplSuccessTitle: {
    fontSize: '1.0625rem',
    fontWeight: '700',
    color: '#15803d',
  },
  iplSuccessCard: {
    width: '100%',
    backgroundColor: '#f0fdf4',
    border: '1px solid #86efac',
    borderRadius: '8px',
    padding: '1rem',
    display: 'flex',
    flexDirection: 'column' as const,
    gap: '0.5rem',
  },
  iplSuccessRow: {
    display: 'flex',
    justifyContent: 'space-between',
    fontSize: '0.875rem',
    color: '#166534',
  },
  iplSuccessLabel: {
    color: '#4ade80',
    fontWeight: '500',
  },
  iplSuccessVal: {
    fontWeight: '600',
    color: '#14532d',
    textAlign: 'right' as const,
  },
  iplSuccessTotal: {
    borderTop: '1px solid #86efac',
    paddingTop: '0.5rem',
    marginTop: '0.25rem',
    fontSize: '1rem',
    fontWeight: '700',
    color: '#15803d',
  },
  iplSuccessDesc: {
    fontSize: '0.8125rem',
    color: '#4b5563',
    fontStyle: 'italic',
  },
  nativeSelect: {
    width: '100%',
    padding: '0.5rem 0.75rem',
    borderRadius: '6px',
    border: '1px solid #d1d5db',
    fontSize: '0.875rem',
    color: '#111827',
    backgroundColor: '#ffffff',
    outline: 'none',
  },

  // ─── Banner row (wraps IPL + Water banners side-by-side) ───────────────────
  bannerRow: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: '1rem',
  },

  // ─── Generate Water styles ──────────────────────────────────────────────────
  waterBanner: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: '1rem',
    backgroundColor: '#eff6ff',
    border: '1px solid #93c5fd',
    borderRadius: '8px',
    padding: '1rem 1.25rem',
  },
  waterBannerTitle: {
    fontSize: '0.9375rem',
    fontWeight: '700',
    color: '#1d4ed8',
  },
  waterInfoPanel: {
    marginTop: '0.625rem',
    backgroundColor: '#eff6ff',
    border: '1px solid #bfdbfe',
    borderRadius: '6px',
    padding: '0.75rem 1rem',
    display: 'flex',
    flexDirection: 'column' as const,
    gap: '0.375rem',
  },
  waterInfoNotice: {
    marginTop: '0.375rem',
    fontSize: '0.75rem',
    color: '#1d4ed8',
    fontStyle: 'italic',
    backgroundColor: '#dbeafe',
    borderRadius: '4px',
    padding: '0.375rem 0.5rem',
  },
  waterNoOwnershipAlert: {
    backgroundColor: '#fef3c7',
    border: '1px solid #fcd34d',
    borderRadius: '6px',
    padding: '0.75rem 1rem',
    fontSize: '0.875rem',
    color: '#92400e',
  },
}
