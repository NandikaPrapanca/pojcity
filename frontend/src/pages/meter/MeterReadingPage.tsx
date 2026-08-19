import React, { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { meterApi } from '@/api/meterApi'
import { ownershipApi } from '@/api/ownershipApi'
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

interface MeterReading {
  id: number
  ownership_id: number
  meter_number: string | null
  reading_date: string
  previous_reading: string
  current_reading: string
  usage: string
  photo_path: string | null
  notes: string | null
  billing_period_start: string
  billing_period_end: string
  created_at: string
  // relations enriched
  customer_id?: number
  customer_name?: string | null
  customer_phone?: string | null
  project_name?: string | null
  ownership_type?: 'residential' | 'commercial'
  cluster_name?: string | null
  block_name?: string | null
  lot_number?: string | null
  water_rate_group_name?: string | null
}

interface OwnershipOption {
  id: number
  customer_name: string | null
  project_name: string | null
  ownership_type: 'residential' | 'commercial'
  cluster_name: string | null
  block_name: string | null
  lot_number: string | null
  water_rate_group_name: string | null
}

interface FormState {
  ownership_id: string
  meter_number: string
  reading_date: string
  previous_reading: string
  current_reading: string
  billing_period_start: string
  billing_period_end: string
  notes: string
}

const emptyForm: FormState = {
  ownership_id: '',
  meter_number: '',
  reading_date: new Date().toISOString().split('T')[0],
  previous_reading: '0.00',
  current_reading: '',
  billing_period_start: '',
  billing_period_end: '',
  notes: '',
}

// ─── Format Helpers ──────────────────────────────────────────────────────────

const fmtDate = (d: string | null) =>
  d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-'

const fmtM3 = (v: string | number | null) =>
  v !== null && v !== undefined && v !== '' ? `${Number(v).toFixed(2)} m³` : '-'

const getPhotoUrl = (photoPath: string | null) => {
  if (!photoPath) return null
  const filename = photoPath.replace(/^.*[\\/]/, '')
  const baseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1'
  return `${baseUrl}/meter-readings/photo/${filename}`
}

export default function MeterReadingPage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  // Filters
  const [filterOwnership, setFilterOwnership] = useState('')
  const [filterPeriod, setFilterPeriod] = useState('')
  const [searchQuery, setSearchQuery] = useState('')

  // Modals
  const [modal, setModal] = useState<{ open: boolean; editing: MeterReading | null }>({
    open: false,
    editing: null,
  })
  const [form, setForm] = useState<FormState>(emptyForm)
  const [photoFile, setPhotoFile] = useState<File | null>(null)
  const [photoPreview, setPhotoPreview] = useState<string | null>(null)
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof FormState | 'photo', string>>>({})
  const [serverError, setServerError] = useState<string | null>(null)

  // Detail & Photo modals
  const [detailTarget, setDetailTarget] = useState<MeterReading | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<MeterReading | null>(null)
  const [viewPhotoUrl, setViewPhotoUrl] = useState<string | null>(null)

  // ── Queries ─────────────────────────────────────────────────────────────────

  const listParams: Record<string, unknown> = {}
  if (filterOwnership) listParams.ownership_id = filterOwnership
  if (filterPeriod) listParams.period = filterPeriod
  if (searchQuery) listParams.search = searchQuery

  const { data: readings, isLoading } = useQuery({
    queryKey: ['meter-readings', listParams],
    queryFn: () => meterApi.list(listParams).then((r) => r.data.data as MeterReading[]),
  })

  const { data: ownerships } = useQuery({
    queryKey: ['ownerships-for-meter'],
    queryFn: () => ownershipApi.list({ active: 'true' }).then((r) => (r.data.data as OwnershipOption[]) ?? []),
  })

  // ── Ownership format helper ──────────────────────────────────────────────────

  const formatOwnershipLabel = (o: OwnershipOption) => {
    const loc =
      o.ownership_type === 'residential'
        ? [o.cluster_name, o.block_name ? `Blok ${o.block_name}` : null, o.lot_number ? `No. ${o.lot_number}` : null]
            .filter(Boolean)
            .join(' - ')
        : o.project_name
    return `${o.customer_name ?? 'Tanpa Nama'} (${loc})`
  }

  // ── Auto-populate latest reading when ownership changes in create mode ───────

  const handleOwnershipChange = async (ownershipId: string) => {
    setForm((prev) => ({ ...prev, ownership_id: ownershipId }))
    setFormErrors((prev) => ({ ...prev, ownership_id: undefined }))

    if (!ownershipId || modal.editing) return

    try {
      const res = await meterApi.latest(Number(ownershipId))
      const latest = res.data.data as MeterReading | null

      if (latest) {
        setForm((prev) => ({
          ...prev,
          ownership_id: ownershipId,
          previous_reading: String(latest.current_reading),
          meter_number: prev.meter_number || latest.meter_number || '',
        }))
      } else {
        setForm((prev) => ({
          ...prev,
          ownership_id: ownershipId,
          previous_reading: '0.00',
        }))
      }
    } catch {
      // Fallback gracefully
      setForm((prev) => ({ ...prev, ownership_id: ownershipId, previous_reading: '0.00' }))
    }
  }

  // ── Live usage calculation ───────────────────────────────────────────────────

  const calculatedUsage = (() => {
    const prev = parseFloat(form.previous_reading)
    const curr = parseFloat(form.current_reading)
    if (!isNaN(prev) && !isNaN(curr) && curr >= prev) {
      return (curr - prev).toFixed(2)
    }
    return null
  })()

  // ── Mutations ───────────────────────────────────────────────────────────────

  const saveMutation = useMutation({
    mutationFn: async (data: { id?: number; payload: FormData }) => {
      if (data.id) {
        return meterApi.update(data.id, data.payload)
      }
      return meterApi.create(data.payload)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['meter-readings'] })
      showToast(modal.editing ? 'Catatan meteran berhasil diperbarui.' : 'Catatan meteran baru berhasil disimpan.', 'success')
      closeModal()
    },
    onError: (err: { response?: { data?: { message?: string; errors?: Record<string, string> } } }) => {
      const msg = err.response?.data?.message || 'Terjadi kesalahan saat menyimpan data.'
      setServerError(msg)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => meterApi.delete(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['meter-readings'] })
      showToast('Catatan meteran berhasil dihapus.', 'success')
      setDeleteTarget(null)
    },
    onError: () => {
      showToast('Gagal menghapus catatan meteran.', 'error')
    },
  })

  // ── Form handlers ───────────────────────────────────────────────────────────

  const openCreateModal = () => {
    setForm(emptyForm)
    setPhotoFile(null)
    setPhotoPreview(null)
    setFormErrors({})
    setServerError(null)
    setModal({ open: true, editing: null })
  }

  const openEditModal = (item: MeterReading) => {
    setForm({
      ownership_id: String(item.ownership_id),
      meter_number: item.meter_number ?? '',
      reading_date: item.reading_date,
      previous_reading: String(item.previous_reading),
      current_reading: String(item.current_reading),
      billing_period_start: item.billing_period_start,
      billing_period_end: item.billing_period_end,
      notes: item.notes ?? '',
    })
    setPhotoFile(null)
    setPhotoPreview(getPhotoUrl(item.photo_path))
    setFormErrors({})
    setServerError(null)
    setModal({ open: true, editing: item })
  }

  const closeModal = () => {
    setModal({ open: false, editing: null })
    setForm(emptyForm)
    setPhotoFile(null)
    setPhotoPreview(null)
    setFormErrors({})
    setServerError(null)
  }

  const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) {
      if (file.size > 5 * 1024 * 1024) {
        setFormErrors((prev) => ({ ...prev, photo: 'Ukuran foto maksimal 5 MB.' }))
        return
      }
      setPhotoFile(file)
      setPhotoPreview(URL.createObjectURL(file))
    }
  }

  const validateForm = (): boolean => {
    const errors: Partial<Record<keyof FormState, string>> = {}

    if (!form.ownership_id) errors.ownership_id = 'Kepemilikan wajib dipilih.'
    if (!form.reading_date) errors.reading_date = 'Tanggal pencatatan wajib diisi.'

    const prev = parseFloat(form.previous_reading)
    const curr = parseFloat(form.current_reading)

    if (isNaN(prev) || prev < 0) errors.previous_reading = 'Stand meter lalu harus berupa angka >= 0.'
    if (isNaN(curr) || curr < 0) {
      errors.current_reading = 'Stand meter kini harus berupa angka >= 0.'
    } else if (!isNaN(prev) && curr < prev) {
      errors.current_reading = 'Stand meter kini tidak boleh lebih kecil dari stand lalu.'
    }

    if (!form.billing_period_start) errors.billing_period_start = 'Awal periode wajib diisi.'
    if (!form.billing_period_end) {
      errors.billing_period_end = 'Akhir periode wajib diisi.'
    } else if (form.billing_period_start && form.billing_period_end < form.billing_period_start) {
      errors.billing_period_end = 'Akhir periode tidak boleh sebelum awal periode.'
    }

    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setServerError(null)
    if (!validateForm()) return

    const formData = new FormData()
    formData.append('ownership_id', form.ownership_id)
    formData.append('reading_date', form.reading_date)
    formData.append('previous_reading', form.previous_reading)
    formData.append('current_reading', form.current_reading)
    formData.append('billing_period_start', form.billing_period_start)
    formData.append('billing_period_end', form.billing_period_end)

    if (form.meter_number) formData.append('meter_number', form.meter_number)
    if (form.notes) formData.append('notes', form.notes)
    if (photoFile) formData.append('photo', photoFile)

    saveMutation.mutate({ id: modal.editing?.id, payload: formData })
  }

  return (
    <div style={styles.page}>
      <PageHeader
        title="Pencatatan Meteran Air"
        subtitle="Kelola riwayat pencatatan meter air, hitung pemakaian otomatis, dan upload bukti foto stand meter."
        onAdd={openCreateModal}
        addLabel="+ Catat Meteran Baru"
      />

      {/* Filter Bar */}
      <Card style={styles.filterCard}>
        <div style={styles.filterRow}>
          <div style={{ flex: 2 }}>
            <Input
              placeholder="Cari nama customer, nomor meter..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>

          <div style={{ flex: 2 }}>
            <Select
              value={filterOwnership}
              onChange={(e) => setFilterOwnership(e.target.value)}
              options={[
                { value: '', label: 'Semua Unit / Kepemilikan' },
                ...(ownerships ?? []).map((o) => ({
                  value: String(o.id),
                  label: formatOwnershipLabel(o),
                })),
              ]}
            />
          </div>

          <div style={{ flex: 1 }}>
            <Input
              type="month"
              value={filterPeriod}
              onChange={(e) => setFilterPeriod(e.target.value)}
            />
          </div>

          {(filterOwnership || filterPeriod || searchQuery) && (
            <Button
              variant="secondary"
              onClick={() => {
                setFilterOwnership('')
                setFilterPeriod('')
                setSearchQuery('')
              }}
            >
              Reset Filter
            </Button>
          )}
        </div>
      </Card>

      {/* Main Table */}
      <Card style={{ marginTop: '1.25rem' }}>
        {isLoading ? (
          <Spinner />
        ) : !readings || readings.length === 0 ? (
          <EmptyState message="Belum ada data pencatatan stand meter air yang terdaftar pada sistem." />
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table style={styles.table}>
              <thead>
                <tr style={styles.tableHeaderRow}>
                  <th style={styles.th}>Customer & Properti</th>
                  <th style={styles.th}>No. Meter</th>
                  <th style={styles.th}>Tgl Catat</th>
                  <th style={styles.th}>Periode Tagihan</th>
                  <th style={{ ...styles.th, textAlign: 'right' }}>Stand Lalu</th>
                  <th style={{ ...styles.th, textAlign: 'right' }}>Stand Kini</th>
                  <th style={{ ...styles.th, textAlign: 'right' }}>Pemakaian</th>
                  <th style={{ ...styles.th, textAlign: 'center' }}>Foto</th>
                  <th style={{ ...styles.th, textAlign: 'center' }}>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {readings.map((r) => {
                  const photoUrl = getPhotoUrl(r.photo_path)
                  return (
                    <tr key={r.id} style={styles.tableRow}>
                      <td style={styles.td}>
                        <div style={styles.customerName}>{r.customer_name ?? 'Tanpa Nama'}</div>
                        <div style={styles.propertySub}>
                          {r.ownership_type === 'residential'
                            ? [r.cluster_name, r.block_name ? `Blok ${r.block_name}` : null, r.lot_number ? `No. ${r.lot_number}` : null]
                                .filter(Boolean)
                                .join(' - ')
                            : r.project_name}
                        </div>
                      </td>
                      <td style={styles.td}>
                        <code style={styles.meterNumberCode}>{r.meter_number || '-'}</code>
                      </td>
                      <td style={styles.td}>{fmtDate(r.reading_date)}</td>
                      <td style={styles.td}>
                        <span style={styles.periodText}>
                          {fmtDate(r.billing_period_start)} s/d {fmtDate(r.billing_period_end)}
                        </span>
                      </td>
                      <td style={{ ...styles.td, textAlign: 'right' }}>{fmtM3(r.previous_reading)}</td>
                      <td style={{ ...styles.td, textAlign: 'right', fontWeight: '600' }}>
                        {fmtM3(r.current_reading)}
                      </td>
                      <td style={{ ...styles.td, textAlign: 'right' }}>
                        <Badge label={fmtM3(r.usage)} color="green" />
                      </td>
                      <td style={{ ...styles.td, textAlign: 'center' }}>
                        {photoUrl ? (
                          <button
                            type="button"
                            onClick={() => setViewPhotoUrl(photoUrl)}
                            style={styles.photoThumbButton}
                            title="Lihat foto meteran"
                          >
                            <img src={photoUrl} alt="Foto meter" style={styles.photoThumb} />
                          </button>
                        ) : (
                          <span style={styles.noPhoto}>-</span>
                        )}
                      </td>
                      <td style={{ ...styles.td, textAlign: 'center' }}>
                        <div style={styles.actionGroup}>
                          <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => setDetailTarget(r)}
                          >
                            Detail
                          </Button>
                          <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => openEditModal(r)}
                          >
                            Ubah
                          </Button>
                          <Button
                            variant="danger"
                            size="sm"
                            onClick={() => setDeleteTarget(r)}
                          >
                            Hapus
                          </Button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* ── Form Modal (Create / Edit) ────────────────────────────────────────── */}
      <Modal
        open={modal.open}
        title={modal.editing ? 'Ubah Catatan Meteran' : 'Pencatatan Stand Meter Air'}
        onClose={closeModal}
        width={580}
      >
        <form onSubmit={handleSubmit} style={styles.form}>
          {serverError && (
            <div style={styles.errorAlert}>
              <span>⚠️ {serverError}</span>
            </div>
          )}

          {/* Unit / Ownership Selector */}
          <div>
            <label style={styles.label}>
              Unit / Kepemilikan <span style={styles.req}>*</span>
            </label>
            <Select
              value={form.ownership_id}
              onChange={(e) => handleOwnershipChange(e.target.value)}
              disabled={!!modal.editing}
              options={[
                { value: '', label: '-- Pilih Customer & Properti --' },
                ...(ownerships ?? []).map((o) => ({
                  value: String(o.id),
                  label: formatOwnershipLabel(o),
                })),
              ]}
            />
            {formErrors.ownership_id && <span style={styles.fieldError}>{formErrors.ownership_id}</span>}
          </div>

          <div style={styles.grid2}>
            <div>
              <label style={styles.label}>Nomor Meteran (Opsional)</label>
              <Input
                placeholder="Contoh: MTR-0045"
                value={form.meter_number}
                onChange={(e) => setForm({ ...form, meter_number: e.target.value })}
              />
            </div>
            <div>
              <label style={styles.label}>
                Tanggal Pencatatan <span style={styles.req}>*</span>
              </label>
              <Input
                type="date"
                value={form.reading_date}
                onChange={(e) => setForm({ ...form, reading_date: e.target.value })}
              />
              {formErrors.reading_date && <span style={styles.fieldError}>{formErrors.reading_date}</span>}
            </div>
          </div>

          {/* Meter Readings and Live Calculation Preview */}
          <div style={styles.meterInputSection}>
            <div style={styles.grid2}>
              <div>
                <label style={styles.label}>
                  Stand Meter Lalu (m³) <span style={styles.req}>*</span>
                </label>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={form.previous_reading}
                  onChange={(e) => setForm({ ...form, previous_reading: e.target.value })}
                />
                {formErrors.previous_reading && (
                  <span style={styles.fieldError}>{formErrors.previous_reading}</span>
                )}
              </div>
              <div>
                <label style={styles.label}>
                  Stand Meter Kini (m³) <span style={styles.req}>*</span>
                </label>
                <Input
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  value={form.current_reading}
                  onChange={(e) => setForm({ ...form, current_reading: e.target.value })}
                />
                {formErrors.current_reading && (
                  <span style={styles.fieldError}>{formErrors.current_reading}</span>
                )}
              </div>
            </div>

            {/* Calculated Usage Badge Display */}
            <div style={styles.calculatedUsageBox}>
              <span style={styles.calcLabel}>Estimasi Pemakaian:</span>
              {calculatedUsage !== null ? (
                <span style={styles.calcValue}>{calculatedUsage} m³</span>
              ) : (
                <span style={styles.calcPlaceholder}>
                  Masukkan stand kini &ge; stand lalu untuk kalkulasi
                </span>
              )}
            </div>
          </div>

          {/* Billing Period */}
          <div style={styles.grid2}>
            <div>
              <label style={styles.label}>
                Awal Periode Tagihan <span style={styles.req}>*</span>
              </label>
              <Input
                type="date"
                value={form.billing_period_start}
                onChange={(e) => setForm({ ...form, billing_period_start: e.target.value })}
              />
              {formErrors.billing_period_start && (
                <span style={styles.fieldError}>{formErrors.billing_period_start}</span>
              )}
            </div>
            <div>
              <label style={styles.label}>
                Akhir Periode Tagihan <span style={styles.req}>*</span>
              </label>
              <Input
                type="date"
                value={form.billing_period_end}
                onChange={(e) => setForm({ ...form, billing_period_end: e.target.value })}
              />
              {formErrors.billing_period_end && (
                <span style={styles.fieldError}>{formErrors.billing_period_end}</span>
              )}
            </div>
          </div>

          {/* Photo Upload with Preview */}
          <div>
            <label style={styles.label}>Foto Stand Meter (Bukti Fisik)</label>
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              onChange={handlePhotoChange}
              style={styles.fileInput}
            />
            {photoPreview && (
              <div style={styles.previewContainer}>
                <img src={photoPreview} alt="Preview" style={styles.previewImage} />
                <button
                  type="button"
                  onClick={() => {
                    setPhotoFile(null)
                    setPhotoPreview(null)
                  }}
                  style={styles.removePhotoBtn}
                >
                  Hapus Foto
                </button>
              </div>
            )}
          </div>

          <div>
            <label style={styles.label}>Catatan (Opsional)</label>
            <Textarea
              placeholder="Catatan kondisi meteran, kendala pembacaan, dsb."
              rows={2}
              value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
            />
          </div>

          <div style={styles.modalActions}>
            <Button type="button" variant="secondary" onClick={closeModal}>
              Batal
            </Button>
            <Button type="submit" variant="primary" disabled={saveMutation.isPending}>
              {saveMutation.isPending ? 'Menyimpan...' : modal.editing ? 'Simpan Perubahan' : 'Catat Stand Meter'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* ── Detail Modal ──────────────────────────────────────────────────────── */}
      {detailTarget && (
        <Modal open={!!detailTarget} title="Detail Pencatatan Meteran" onClose={() => setDetailTarget(null)}>
          <div style={styles.detailBody}>
            <div style={styles.detailSection}>
              <div style={styles.detailRow}>
                <span style={styles.detailKey}>Customer</span>
                <span style={styles.detailVal}>{detailTarget.customer_name ?? '-'}</span>
              </div>
              <div style={styles.detailRow}>
                <span style={styles.detailKey}>Unit / Properti</span>
                <span style={styles.detailVal}>
                  {detailTarget.ownership_type === 'residential'
                    ? [detailTarget.cluster_name, detailTarget.block_name ? `Blok ${detailTarget.block_name}` : null, detailTarget.lot_number ? `No. ${detailTarget.lot_number}` : null]
                        .filter(Boolean)
                        .join(' - ')
                    : detailTarget.project_name}
                </span>
              </div>
              <div style={styles.detailRow}>
                <span style={styles.detailKey}>Nomor Meteran</span>
                <span style={styles.detailVal}>{detailTarget.meter_number || '-'}</span>
              </div>
              <div style={styles.detailRow}>
                <span style={styles.detailKey}>Tanggal Pencatatan</span>
                <span style={styles.detailVal}>{fmtDate(detailTarget.reading_date)}</span>
              </div>
              <div style={styles.detailRow}>
                <span style={styles.detailKey}>Periode Tagihan</span>
                <span style={styles.detailVal}>
                  {fmtDate(detailTarget.billing_period_start)} s/d {fmtDate(detailTarget.billing_period_end)}
                </span>
              </div>
            </div>

            <div style={styles.detailMeterBox}>
              <div style={styles.detailMeterCol}>
                <span style={styles.meterLabel}>Stand Lalu</span>
                <span style={styles.meterVal}>{fmtM3(detailTarget.previous_reading)}</span>
              </div>
              <div style={styles.detailMeterCol}>
                <span style={styles.meterLabel}>Stand Kini</span>
                <span style={styles.meterVal}>{fmtM3(detailTarget.current_reading)}</span>
              </div>
              <div style={styles.detailMeterCol}>
                <span style={styles.meterLabel}>Total Pemakaian</span>
                <span style={{ ...styles.meterVal, color: '#2d5a3d', fontWeight: '700' }}>
                  {fmtM3(detailTarget.usage)}
                </span>
              </div>
            </div>

            {detailTarget.notes && (
              <div style={styles.detailRow}>
                <span style={styles.detailKey}>Catatan</span>
                <span style={styles.detailVal}>{detailTarget.notes}</span>
              </div>
            )}

            {detailTarget.photo_path && (
              <div>
                <label style={styles.label}>Foto Bukti Stand Meter:</label>
                <div style={styles.detailPhotoBox}>
                  <img
                    src={getPhotoUrl(detailTarget.photo_path)!}
                    alt="Foto Stand Meter"
                    style={styles.detailPhotoImg}
                    onClick={() => setViewPhotoUrl(getPhotoUrl(detailTarget.photo_path)!)}
                  />
                </div>
              </div>
            )}

            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '1.5rem' }}>
              <Button variant="secondary" onClick={() => setDetailTarget(null)}>
                Tutup
              </Button>
            </div>
          </div>
        </Modal>
      )}

      {/* ── Photo Preview Modal ───────────────────────────────────────────────── */}
      {viewPhotoUrl && (
        <Modal open={!!viewPhotoUrl} title="Pratinjau Foto Meteran" onClose={() => setViewPhotoUrl(null)}>
          <div style={{ textAlign: 'center', padding: '1rem' }}>
            <img
              src={viewPhotoUrl}
              alt="Pratinjau"
              style={{ maxWidth: '100%', maxHeight: '70vh', borderRadius: '8px', objectFit: 'contain' }}
            />
          </div>
        </Modal>
      )}

      {/* ── Delete Confirmation Dialog ────────────────────────────────────────── */}
      <ConfirmDialog
        open={!!deleteTarget}
        title="Hapus Catatan Meteran?"
        message={`Apakah Anda yakin ingin menghapus pencatatan meteran tanggal ${fmtDate(deleteTarget?.reading_date ?? '')} untuk customer ${deleteTarget?.customer_name ?? ''}? Data yang dihapus tidak dapat diproses pada tagihan.`}
        loading={deleteMutation.isPending}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
      />

      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}
    </div>
  )
}

// ─── Component Styles ─────────────────────────────────────────────────────────

const styles: Record<string, React.CSSProperties> = {
  page: {
    padding: '1.5rem 2rem',
  },
  filterCard: {
    backgroundColor: '#ffffff',
    padding: '1rem 1.25rem',
  },
  filterRow: {
    display: 'flex',
    gap: '1rem',
    alignItems: 'center',
    flexWrap: 'wrap',
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
    fontSize: '0.875rem',
  },
  tableHeaderRow: {
    borderBottom: '2px solid #e5e7eb',
    backgroundColor: '#f9fafb',
  },
  th: {
    padding: '0.75rem 1rem',
    textAlign: 'left',
    fontWeight: '600',
    color: '#374151',
  },
  tableRow: {
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
  meterNumberCode: {
    backgroundColor: '#f3f4f6',
    padding: '0.2rem 0.4rem',
    borderRadius: '4px',
    fontSize: '0.8rem',
    color: '#374151',
  },
  periodText: {
    fontSize: '0.8rem',
    color: '#4b5563',
  },
  photoThumbButton: {
    border: 'none',
    background: 'none',
    cursor: 'pointer',
    padding: 0,
  },
  photoThumb: {
    width: '36px',
    height: '36px',
    borderRadius: '6px',
    objectFit: 'cover',
    border: '1px solid #e5e7eb',
  },
  noPhoto: {
    color: '#9ca3af',
  },
  actionGroup: {
    display: 'flex',
    gap: '0.375rem',
    justifyContent: 'center',
  },
  form: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  grid2: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: '1rem',
  },
  label: {
    display: 'block',
    fontSize: '0.8125rem',
    fontWeight: '500',
    color: '#374151',
    marginBottom: '0.375rem',
  },
  req: {
    color: '#dc2626',
  },
  fieldError: {
    fontSize: '0.75rem',
    color: '#dc2626',
    marginTop: '0.25rem',
    display: 'block',
  },
  errorAlert: {
    backgroundColor: '#fef2f2',
    border: '1px solid #fecaca',
    color: '#991b1b',
    padding: '0.75rem 1rem',
    borderRadius: '6px',
    fontSize: '0.875rem',
  },
  meterInputSection: {
    backgroundColor: '#f8fafc',
    border: '1px solid #e2e8f0',
    borderRadius: '8px',
    padding: '1rem',
  },
  calculatedUsageBox: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: '0.75rem',
    paddingTop: '0.75rem',
    borderTop: '1px dashed #cbd5e1',
  },
  calcLabel: {
    fontSize: '0.8125rem',
    fontWeight: '500',
    color: '#475569',
  },
  calcValue: {
    fontSize: '1rem',
    fontWeight: '700',
    color: '#2d5a3d',
  },
  calcPlaceholder: {
    fontSize: '0.75rem',
    color: '#94a3b8',
  },
  fileInput: {
    width: '100%',
    padding: '0.5rem',
    fontSize: '0.8125rem',
    border: '1px solid #d1d5db',
    borderRadius: '6px',
  },
  previewContainer: {
    marginTop: '0.5rem',
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
  },
  previewImage: {
    width: '80px',
    height: '80px',
    objectFit: 'cover',
    borderRadius: '6px',
    border: '1px solid #e5e7eb',
  },
  removePhotoBtn: {
    border: 'none',
    background: 'none',
    color: '#dc2626',
    fontSize: '0.75rem',
    cursor: 'pointer',
    textDecoration: 'underline',
  },
  modalActions: {
    display: 'flex',
    justifyContent: 'flex-end',
    gap: '0.75rem',
    marginTop: '1.25rem',
  },
  detailBody: {
    display: 'flex',
    flexDirection: 'column',
    gap: '1rem',
  },
  detailSection: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
    backgroundColor: '#f9fafb',
    padding: '1rem',
    borderRadius: '8px',
  },
  detailRow: {
    display: 'flex',
    justifyContent: 'space-between',
    fontSize: '0.875rem',
  },
  detailKey: {
    color: '#6b7280',
  },
  detailVal: {
    fontWeight: '500',
    color: '#111827',
    textAlign: 'right',
  },
  detailMeterBox: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr 1fr',
    gap: '0.5rem',
    backgroundColor: '#f0fdf4',
    border: '1px solid #bbf7d0',
    borderRadius: '8px',
    padding: '1rem',
    textAlign: 'center',
  },
  detailMeterCol: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.25rem',
  },
  meterLabel: {
    fontSize: '0.75rem',
    color: '#4b5563',
  },
  meterVal: {
    fontSize: '1rem',
    fontWeight: '600',
    color: '#111827',
  },
  detailPhotoBox: {
    marginTop: '0.5rem',
    textAlign: 'center',
  },
  detailPhotoImg: {
    maxWidth: '100%',
    maxHeight: '220px',
    borderRadius: '8px',
    border: '1px solid #e5e7eb',
    cursor: 'pointer',
  },
}
