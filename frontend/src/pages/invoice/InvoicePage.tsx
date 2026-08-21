import React, { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { billingApi } from '@/api/billingApi'
import { invoiceApi, openInvoicePdf, openInvoiceReceiptPdf, type Invoice, type TaxPreview } from '@/api/invoiceApi'
import { paymentApi } from '@/api/paymentApi'
import { downloadInvoicesExcel } from '@/api/reportApi'
import { useToast } from '@/hooks/useToast'
import PageHeader from '@/components/ui/PageHeader'
import Card from '@/components/ui/Card'
import Button from '@/components/ui/Button'
import Modal from '@/components/ui/Modal'
import Spinner from '@/components/ui/Spinner'
import Toast from '@/components/ui/Toast'
import EmptyState from '@/components/ui/EmptyState'
import Badge from '@/components/ui/Badge'

// ─── Interfaces ───────────────────────────────────────────────────────────────

interface BillingItem {
  id: number
  ownership_id: number
  billing_type: string
  billing_period_start: string
  billing_period_end: string
  description: string
  quantity: string | number
  unit: string
  unit_price: string | number
  subtotal: string | number
  apply_tax: number
  notes?: string | null
  status: 'draft' | 'invoiced' | 'cancelled'
  customer_name?: string | null
  project_name?: string | null
  ownership_type?: string | null
  cluster_name?: string | null
  block_name?: string | null
  lot_number?: string | null
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const formatRupiah = (val: string | number | null | undefined): string => {
  if (val === null || val === undefined || val === '') return 'Rp 0'
  const num = Number(val)
  return 'Rp ' + Math.round(num).toLocaleString('id-ID')
}

const formatDate = (d: string) => {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

const getTypeBadgeColor = (type: string): 'green' | 'gray' | 'blue' | 'red' => {
  switch (type.toLowerCase()) {
    case 'ipl': return 'blue'
    case 'water': return 'green'
    case 'electricity': return 'gray'
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

const getInvoiceStatusColor = (status: string): 'green' | 'gray' | 'blue' | 'red' => {
  switch (status) {
    case 'paid': return 'green'
    case 'sent': return 'blue'
    case 'overdue': return 'red'
    case 'cancelled': return 'red'
    default: return 'gray'
  }
}

const getInvoiceStatusLabel = (status: string): string => {
  switch (status) {
    case 'draft': return 'Draft'
    case 'sent': return 'Terkirim'
    case 'paid': return 'Lunas'
    case 'overdue': return 'Jatuh Tempo'
    case 'cancelled': return 'Dibatalkan'
    default: return status
  }
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function InvoicePage() {
  const qc = useQueryClient()
  const { toast, showToast, hideToast } = useToast()

  // ── Selection state ──────────────────────────────────────────────────────────
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  // ── Tax preview state (from backend, not calculated) ─────────────────────────
  const [taxPreview, setTaxPreview] = useState<TaxPreview | null>(null)
  const [taxPreviewLoading, setTaxPreviewLoading] = useState(false)
  const [taxPreviewError, setTaxPreviewError] = useState<string | null>(null)

  // ── Generate modal state ─────────────────────────────────────────────────────
  const [generateModal, setGenerateModal] = useState(false)
  const [genNotes, setGenNotes] = useState('')
  const [genError, setGenError] = useState<string | null>(null)

  // ── Invoice detail modal ─────────────────────────────────────────────────────
  const [detailInvoice, setDetailInvoice] = useState<Invoice | null>(null)

  // ── PDF loading state (keyed by invoice id) ──────────────────────────────────
  const [pdfLoading, setPdfLoading] = useState<Record<number, boolean>>({})

  // ── Kwitansi / Receipt PDF loading state (keyed by invoice id) ───────────────
  const [receiptLoading, setReceiptLoading] = useState<Record<number, boolean>>({})

  // ── WhatsApp loading state (keyed by invoice id) ──────────────────────────
  const [waLoading, setWaLoading] = useState<Record<number, boolean>>({})

  // ── Payment modal state ──────────────────────────────────────────────────────
  const [paymentModal, setPaymentModal] = useState(false)
  const [payingInvoice, setPayingInvoice] = useState<Invoice | null>(null)
  const [paymentForm, setPaymentForm] = useState({
    payment_date: new Date().toISOString().split('T')[0],
    payment_method: 'Transfer BCA',
    amount: '',
    reference_number: '',
    notes: '',
  })
  const [paymentError, setPaymentError] = useState<string | null>(null)

  // ─── Queries ──────────────────────────────────────────────────────────────────

  const { data: draftItems = [], isLoading: draftLoading } = useQuery<BillingItem[]>({
    queryKey: ['billing-items-draft'],
    queryFn: async () => {
      const res = await billingApi.list({ status: 'draft' })
      return res.data.data
    },
  })

  const { data: invoices = [], isLoading: invoicesLoading } = useQuery<Invoice[]>({
    queryKey: ['invoices'],
    queryFn: async () => {
      const res = await invoiceApi.list()
      return res.data.data
    },
  })

  // ─── Preview Tax (fires whenever selection changes) ───────────────────────────

  useEffect(() => {
    const ids = Array.from(selectedIds)

    if (ids.length === 0) {
      setTaxPreview(null)
      setTaxPreviewError(null)
      return
    }

    setTaxPreviewLoading(true)
    setTaxPreviewError(null)

    invoiceApi
      .previewTax(ids)
      .then((res) => {
        setTaxPreview(res.data.data)
      })
      .catch((err: unknown) => {
        const e = err as { response?: { data?: { message?: string } } }
        setTaxPreviewError(e?.response?.data?.message || 'Gagal menghitung perkiraan pajak.')
        setTaxPreview(null)
      })
      .finally(() => setTaxPreviewLoading(false))
  }, [selectedIds])

  // ─── Mutations ────────────────────────────────────────────────────────────────

  const generateMutation = useMutation({
    mutationFn: () =>
      invoiceApi.generate({
        billing_item_ids: Array.from(selectedIds),
        notes: genNotes.trim() || undefined,
      }),
    onSuccess: (res) => {
      const inv = res.data.data
      qc.invalidateQueries({ queryKey: ['billing-items-draft'] })
      qc.invalidateQueries({ queryKey: ['billing-items'] })
      qc.invalidateQueries({ queryKey: ['invoices'] })
      setSelectedIds(new Set())
      setTaxPreview(null)
      setGenerateModal(false)
      setGenNotes('')
      showToast(`Invoice ${inv.invoice_number} berhasil diterbitkan!`, 'success')
    },
    onError: (err: unknown) => {
      const e = err as { response?: { data?: { message?: string } } }
      setGenError(e?.response?.data?.message || 'Gagal menerbitkan invoice.')
    },
  })

  // ─── Selection Handlers ───────────────────────────────────────────────────────

  const toggleItem = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const toggleAll = () => {
    if (selectedIds.size === draftItems.length) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(draftItems.map((i) => i.id)))
    }
  }

  const openGenerateModal = () => {
    setGenNotes('')
    setGenError(null)
    setGenerateModal(true)
  }

  const selectedItems = draftItems.filter((i) => selectedIds.has(i.id))

  // ─── PDF Handler ─────────────────────────────────────────────────────────────

  const handleOpenPdf = async (inv: Invoice) => {
    setPdfLoading((prev) => ({ ...prev, [inv.id]: true }))
    try {
      await openInvoicePdf(inv.id)
    } catch {
      showToast(`Gagal membuka PDF untuk invoice ${inv.invoice_number}.`, 'error')
    } finally {
      setPdfLoading((prev) => ({ ...prev, [inv.id]: false }))
    }
  }

  // ─── WhatsApp Handler ────────────────────────────────────────────────────────

  const handleSendWhatsApp = async (inv: Invoice) => {
    setWaLoading((prev) => ({ ...prev, [inv.id]: true }))
    try {
      const res = await invoiceApi.sendWhatsApp(inv.id)
      const phone = res.data.data?.phone ?? '—'
      showToast(
        `✅ Pesan WhatsApp untuk invoice ${inv.invoice_number} berhasil dikirim ke ${phone} (simulasi).`,
        'success'
      )
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      showToast(
        e?.response?.data?.message ?? `Gagal mengirim WhatsApp untuk invoice ${inv.invoice_number}.`,
        'error'
      )
    } finally {
      setWaLoading((prev) => ({ ...prev, [inv.id]: false }))
    }
  }

  // ─── Kwitansi / Receipt PDF Handler ──────────────────────────────────────────

  const handleOpenReceipt = async (inv: Invoice) => {
    setReceiptLoading((prev) => ({ ...prev, [inv.id]: true }))
    try {
      await openInvoiceReceiptPdf(inv.id)
    } catch {
      showToast(`Gagal membuka Kwitansi untuk invoice ${inv.invoice_number}.`, 'error')
    } finally {
      setReceiptLoading((prev) => ({ ...prev, [inv.id]: false }))
    }
  }

  // ─── Excel Export Handler ────────────────────────────────────────────────────
  const [exportingExcel, setExportingExcel] = useState(false)

  const handleExportExcel = async () => {
    setExportingExcel(true)
    try {
      await downloadInvoicesExcel()
      showToast('✅ Berhasil mengunduh laporan rekapitulasi invoice Excel.', 'success')
    } catch {
      showToast('Gagal mengunduh file Excel.', 'error')
    } finally {
      setExportingExcel(false)
    }
  }

  // ─── Payment Modal & Mutation ────────────────────────────────────────────────

  const openPayModal = (inv: Invoice) => {
    setPayingInvoice(inv)
    setPaymentForm({
      payment_date: new Date().toISOString().split('T')[0],
      payment_method: 'Transfer BCA',
      amount: String(inv.grand_total),
      reference_number: '',
      notes: '',
    })
    setPaymentError(null)
    setPaymentModal(true)
  }

  const paymentMutation = useMutation({
    mutationFn: () => {
      if (!payingInvoice) throw new Error('No invoice selected')
      return paymentApi.create({
        invoice_id: payingInvoice.id,
        payment_date: paymentForm.payment_date,
        payment_method: paymentForm.payment_method,
        amount: Number(paymentForm.amount) || Number(payingInvoice.grand_total),
        reference_number: paymentForm.reference_number.trim() || undefined,
        notes: paymentForm.notes.trim() || undefined,
      })
    },
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['invoices'] })
      qc.invalidateQueries({ queryKey: ['dashboard-summary'] })
      setPaymentModal(false)
      setPayingInvoice(null)
      showToast(
        `✅ Pembayaran untuk invoice ${res.data.data.invoice.invoice_number} berhasil dicatat. Invoice telah Lunas!`,
        'success'
      )
    },
    onError: (err: unknown) => {
      const e = err as { response?: { data?: { message?: string } } }
      setPaymentError(e?.response?.data?.message || 'Gagal mencatat pembayaran.')
    },
  })

  // ── Ownership consistency check (warn if mixed ownerships selected) ──────────
  const selectedOwnershipIds = [...new Set(selectedItems.map((i) => i.ownership_id))]
  const mixedOwnership = selectedOwnershipIds.length > 1

  // ─── Render ───────────────────────────────────────────────────────────────────

  return (
    <div style={s.container}>
      {toast && <Toast message={toast.message} type={toast.type} onClose={hideToast} />}

      <PageHeader
        title="Invoice"
        subtitle="Pilih item tagihan berstatus Draft, cek perkiraan pajak, lalu terbitkan invoice."
      />

      {/* ── Section 1: Draft Items Table ─────────────────────────────────── */}
      <section style={s.section}>
        <div style={s.sectionHeader}>
          <div>
            <h2 style={s.sectionTitle}>Item Tagihan Belum Diinvoice</h2>
            <p style={s.sectionSub}>
              Centang item yang ingin digabungkan dalam satu invoice.
              Semua item yang dipilih harus dari kepemilikan yang sama.
            </p>
          </div>
          <div style={s.selectionBadge}>
            {selectedIds.size > 0 && (
              <span style={s.selectionChip}>{selectedIds.size} item dipilih</span>
            )}
          </div>
        </div>

        <Card>
          {draftLoading ? (
            <div style={s.loadingRow}>
              <Spinner />
              <span style={s.loadingText}>Memuat item tagihan...</span>
            </div>
          ) : draftItems.length === 0 ? (
            <EmptyState message="Tidak ada item tagihan berstatus Draft. Buat item tagihan terlebih dahulu di halaman 'Item Tagihan'." />
          ) : (
            <div style={{ overflowX: 'auto' }}>
              <table style={s.table}>
                <thead>
                  <tr>
                    <th style={{ ...s.th, width: '44px' }}>
                      <input
                        id="chk-select-all"
                        type="checkbox"
                        style={s.checkbox}
                        checked={selectedIds.size === draftItems.length && draftItems.length > 0}
                        onChange={toggleAll}
                        aria-label="Pilih semua item"
                      />
                    </th>
                    <th style={s.th}>Customer &amp; Unit</th>
                    <th style={s.th}>Jenis</th>
                    <th style={s.th}>Periode</th>
                    <th style={s.th}>Deskripsi</th>
                    <th style={{ ...s.th, textAlign: 'right' }}>Subtotal</th>
                    <th style={{ ...s.th, textAlign: 'center' }}>Pajak</th>
                  </tr>
                </thead>
                <tbody>
                  {draftItems.map((item) => {
                    const checked = selectedIds.has(item.id)
                    return (
                      <tr
                        key={item.id}
                        style={{ ...s.tr, ...(checked ? s.trSelected : {}) }}
                        onClick={() => toggleItem(item.id)}
                      >
                        <td style={s.td} onClick={(e) => e.stopPropagation()}>
                          <input
                            id={`chk-item-${item.id}`}
                            type="checkbox"
                            style={s.checkbox}
                            checked={checked}
                            onChange={() => toggleItem(item.id)}
                            aria-label={`Pilih item ${item.description}`}
                          />
                        </td>
                        <td style={s.td}>
                          <div style={s.customerName}>{item.customer_name || '—'}</div>
                          <div style={s.subText}>
                            {item.project_name}
                            {item.ownership_type === 'residential' && item.cluster_name
                              ? ` · ${item.cluster_name} ${item.block_name || ''}/${item.lot_number || ''}`.trim()
                              : ''}
                          </div>
                        </td>
                        <td style={s.td}>
                          <Badge
                            label={getTypeLabel(item.billing_type)}
                            color={getTypeBadgeColor(item.billing_type)}
                          />
                        </td>
                        <td style={s.td}>
                          <div style={s.periodText}>{item.billing_period_start}</div>
                          <div style={s.subText}>s/d {item.billing_period_end}</div>
                        </td>
                        <td style={s.td}>
                          <div style={s.descText}>{item.description}</div>
                        </td>
                        <td style={{ ...s.td, textAlign: 'right', fontWeight: 600, whiteSpace: 'nowrap' }}>
                          {formatRupiah(item.subtotal)}
                        </td>
                        <td style={{ ...s.td, textAlign: 'center' }}>
                          {Number(item.apply_tax) === 1 ? (
                            <span style={s.taxYes}>PPN</span>
                          ) : (
                            <span style={s.taxNo}>Non-PPN</span>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </section>

      {/* ── Section 2: Tax Preview Panel ─────────────────────────────────── */}
      {selectedIds.size > 0 && (
        <section style={s.section}>
          <h2 style={s.sectionTitle}>Perkiraan Tampilan Pajak</h2>
          <p style={s.sectionSub}>
            Nilai ini dihitung oleh backend — bukan estimasi dari browser.
            Klik "Generate Invoice" untuk menerbitkan dengan nilai yang persis sama.
          </p>

          {mixedOwnership && (
            <div style={s.warningBanner}>
              ⚠️ Item yang dipilih berasal dari {selectedOwnershipIds.length} kepemilikan berbeda.
              Semua item dalam satu invoice harus dari kepemilikan yang sama.
            </div>
          )}

          <div style={s.taxPanelGrid}>
            {/* Tax Breakdown Card */}
            <Card>
              <div style={s.taxCardTitle}>Rincian Pajak (DPP Nilai Lain — 12% PPN)</div>
              {taxPreviewLoading ? (
                <div style={s.loadingRow}>
                  <Spinner />
                  <span style={s.loadingText}>Menghitung...</span>
                </div>
              ) : taxPreviewError ? (
                <div style={s.errorText}>{taxPreviewError}</div>
              ) : taxPreview ? (
                <div style={s.taxRows}>
                  <div style={s.taxRow}>
                    <span style={s.taxLabel}>Subtotal Kena Pajak</span>
                    <span style={s.taxValue}>{formatRupiah(taxPreview.subtotal_taxable)}</span>
                  </div>
                  {taxPreview.subtotal_nontaxable > 0 && (
                    <div style={s.taxRow}>
                      <span style={s.taxLabel}>Subtotal Non-Pajak</span>
                      <span style={s.taxValue}>{formatRupiah(taxPreview.subtotal_nontaxable)}</span>
                    </div>
                  )}
                  <div style={{ ...s.taxRow, borderTop: '1px solid #e5e7eb', paddingTop: '0.75rem', marginTop: '0.25rem' }}>
                    <span style={s.taxLabel}>Subtotal DPP</span>
                    <span style={s.taxValue}>{formatRupiah(taxPreview.subtotal_dpp)}</span>
                  </div>
                  <div style={s.taxRow}>
                    <span style={s.taxLabel}>
                      DPP Nilai Lain{' '}
                      <span style={s.taxFormula}>(11/12 × Subtotal Kena Pajak)</span>
                    </span>
                    <span style={s.taxValue}>{formatRupiah(taxPreview.dpp_nilai_lain)}</span>
                  </div>
                  <div style={s.taxRow}>
                    <span style={s.taxLabel}>
                      PPN <span style={s.taxFormula}>(12% × DPP Nilai Lain)</span>
                    </span>
                    <span style={{ ...s.taxValue, color: '#d97706' }}>{formatRupiah(taxPreview.ppn_amount)}</span>
                  </div>
                  <div style={{ ...s.taxRow, borderTop: '2px solid #d1fae5', paddingTop: '0.75rem', marginTop: '0.25rem' }}>
                    <span style={{ ...s.taxLabel, fontWeight: 700, color: '#111827', fontSize: '0.95rem' }}>
                      Grand Total
                    </span>
                    <span style={{ ...s.taxValue, fontWeight: 700, color: '#059669', fontSize: '1.05rem' }}>
                      {formatRupiah(taxPreview.grand_total)}
                    </span>
                  </div>
                </div>
              ) : null}
            </Card>

            {/* Action Card */}
            <Card>
              <div style={s.taxCardTitle}>Terbitkan Invoice</div>
              <div style={s.actionCardBody}>
                <div style={s.actionItemCount}>
                  <span style={s.actionItemNum}>{selectedIds.size}</span>
                  <span style={s.actionItemLabel}>item tagihan dipilih</span>
                </div>

                {taxPreview && (
                  <div style={s.actionGrandTotal}>
                    <div style={s.actionGtLabel}>Total Invoice</div>
                    <div style={s.actionGtValue}>{formatRupiah(taxPreview.grand_total)}</div>
                  </div>
                )}

                <Button
                  id="btn-generate-invoice"
                  variant="primary"
                  onClick={openGenerateModal}
                  disabled={selectedIds.size === 0 || mixedOwnership || taxPreviewLoading}
                  style={{ width: '100%', marginTop: '1rem' }}
                >
                  Generate Invoice
                </Button>

                {mixedOwnership && (
                  <p style={s.actionWarning}>
                    Pilih hanya item dari satu kepemilikan.
                  </p>
                )}
              </div>
            </Card>
          </div>
        </section>
      )}

      {/* ── Section 3: Generated Invoices Table ──────────────────────────── */}
      <section style={s.section}>
        <div style={s.sectionHeader}>
          <div>
            <h2 style={s.sectionTitle}>Invoice yang Sudah Diterbitkan</h2>
            <p style={s.sectionSub}>Daftar faktur tagihan, status pembayaran, unduh PDF, dan kwitansi</p>
          </div>
          <button
            id="btn-export-invoices-excel"
            style={s.exportExcelBtn}
            onClick={handleExportExcel}
            disabled={exportingExcel || invoices.length === 0}
            title="Export Seluruh Invoice ke Format Excel (.xlsx)"
          >
            {exportingExcel ? '⏳ Mengunduh...' : '📊 Export ke Excel'}
          </button>
        </div>

        <Card>
          {invoicesLoading ? (
            <div style={s.loadingRow}>
              <Spinner />
              <span style={s.loadingText}>Memuat invoice...</span>
            </div>
          ) : invoices.length === 0 ? (
            <EmptyState message="Belum ada invoice yang diterbitkan. Pilih item tagihan di atas untuk memulai." />
          ) : (
            <div style={{ overflowX: 'auto' }}>
              <table style={s.table}>
                <thead>
                  <tr>
                    <th style={s.th}>No. Invoice</th>
                    <th style={s.th}>Customer</th>
                    <th style={s.th}>Tgl. Terbit</th>
                    <th style={s.th}>Jatuh Tempo</th>
                    <th style={{ ...s.th, textAlign: 'right' }}>Subtotal DPP</th>
                    <th style={{ ...s.th, textAlign: 'right' }}>PPN</th>
                    <th style={{ ...s.th, textAlign: 'right' }}>Grand Total</th>
                    <th style={s.th}>Status</th>
                    <th style={{ ...s.th, textAlign: 'center' }}>Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {invoices.map((inv) => (
                    <tr key={inv.id} style={s.tr}>
                      <td style={s.td}>
                        <span style={s.invoiceNumber}>{inv.invoice_number}</span>
                      </td>
                      <td style={s.td}>
                        <div style={s.customerName}>{inv.customer_name || '—'}</div>
                        <div style={s.subText}>
                          {inv.project_name}
                          {inv.ownership_type === 'residential' && inv.cluster_name
                            ? ` · ${inv.cluster_name} ${inv.block_name || ''}/${inv.lot_number || ''}`.trim()
                            : ''}
                        </div>
                      </td>
                      <td style={s.td}>{formatDate(inv.issue_date)}</td>
                      <td style={s.td}>{formatDate(inv.due_date)}</td>
                      <td style={{ ...s.td, textAlign: 'right', whiteSpace: 'nowrap' }}>
                        {formatRupiah(inv.subtotal_dpp)}
                      </td>
                      <td style={{ ...s.td, textAlign: 'right', whiteSpace: 'nowrap', color: '#d97706' }}>
                        {formatRupiah(inv.ppn_amount)}
                      </td>
                      <td style={{ ...s.td, textAlign: 'right', fontWeight: 700, color: '#059669', whiteSpace: 'nowrap' }}>
                        {formatRupiah(inv.grand_total)}
                      </td>
                      <td style={s.td}>
                        <Badge
                          label={getInvoiceStatusLabel(inv.status)}
                          color={getInvoiceStatusColor(inv.status)}
                        />
                      </td>
                      <td style={{ ...s.td, textAlign: 'center', whiteSpace: 'nowrap' }}>
                        <button
                          id={`btn-detail-inv-${inv.id}`}
                          style={s.actionBtn}
                          title="Lihat Detail"
                          onClick={() => setDetailInvoice(inv)}
                          aria-label={`Detail invoice ${inv.invoice_number}`}
                        >
                          👁
                        </button>
                        <button
                          id={`btn-pdf-inv-${inv.id}`}
                          style={pdfLoading[inv.id] ? { ...s.actionBtn, ...s.actionBtnDisabled } : s.actionBtnPdf}
                          title="Download Invoice PDF"
                          onClick={() => handleOpenPdf(inv)}
                          disabled={pdfLoading[inv.id]}
                          aria-label={`Download PDF invoice ${inv.invoice_number}`}
                        >
                          {pdfLoading[inv.id] ? '⏳' : '📄'}
                        </button>
                        {inv.status === 'paid' ? (
                          <button
                            id={`btn-receipt-inv-${inv.id}`}
                            style={receiptLoading[inv.id] ? { ...s.actionBtnReceipt, ...s.actionBtnDisabled } : s.actionBtnReceipt}
                            title="Download Kwitansi PDF"
                            onClick={() => handleOpenReceipt(inv)}
                            disabled={receiptLoading[inv.id]}
                            aria-label={`Download Kwitansi invoice ${inv.invoice_number}`}
                          >
                            {receiptLoading[inv.id] ? '⏳' : '🧾 Kwitansi'}
                          </button>
                        ) : inv.status !== 'cancelled' ? (
                          <button
                            id={`btn-pay-inv-${inv.id}`}
                            style={s.actionBtnPay}
                            title="Catat Pembayaran"
                            onClick={() => openPayModal(inv)}
                            aria-label={`Bayar invoice ${inv.invoice_number}`}
                          >
                            💳 Bayar
                          </button>
                        ) : null}
                        <button
                          id={`btn-wa-inv-${inv.id}`}
                          style={waLoading[inv.id] ? { ...s.actionBtnWa, ...s.actionBtnDisabled } : s.actionBtnWa}
                          title="Kirim WhatsApp"
                          onClick={() => handleSendWhatsApp(inv)}
                          disabled={waLoading[inv.id]}
                          aria-label={`Kirim WhatsApp untuk invoice ${inv.invoice_number}`}
                        >
                          {waLoading[inv.id] ? '⏳' : '💬 WA'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </section>

      {/* ── Generate Confirmation Modal ──────────────────────────────────── */}
      <Modal
        open={generateModal}
        title="Konfirmasi Generate Invoice"
        onClose={() => setGenerateModal(false)}
      >
        <div style={s.modalBody}>
          <p style={s.modalDesc}>
            Anda akan menerbitkan invoice untuk{' '}
            <strong>{selectedIds.size} item tagihan</strong> berikut:
          </p>

          {/* Items list */}
          <div style={s.modalItemList}>
            {selectedItems.map((item) => (
              <div key={item.id} style={s.modalItem}>
                <span style={s.modalItemName}>{item.description}</span>
                <span style={s.modalItemAmount}>{formatRupiah(item.subtotal)}</span>
              </div>
            ))}
          </div>

          {/* Tax summary */}
          {taxPreview && (
            <div style={s.modalTaxSummary}>
              <div style={s.modalTaxRow}>
                <span>Subtotal DPP</span>
                <span>{formatRupiah(taxPreview.subtotal_dpp)}</span>
              </div>
              <div style={s.modalTaxRow}>
                <span>DPP Nilai Lain</span>
                <span>{formatRupiah(taxPreview.dpp_nilai_lain)}</span>
              </div>
              <div style={s.modalTaxRow}>
                <span>PPN (12%)</span>
                <span style={{ color: '#d97706' }}>{formatRupiah(taxPreview.ppn_amount)}</span>
              </div>
              <div style={{ ...s.modalTaxRow, fontWeight: 700, color: '#059669', borderTop: '1px solid #d1fae5', paddingTop: '0.5rem', marginTop: '0.25rem' }}>
                <span>Grand Total</span>
                <span>{formatRupiah(taxPreview.grand_total)}</span>
              </div>
            </div>
          )}

          {/* Notes input */}
          <div style={s.notesGroup}>
            <label style={s.notesLabel} htmlFor="gen-notes">
              Catatan (opsional)
            </label>
            <textarea
              id="gen-notes"
              style={s.notesTextarea}
              value={genNotes}
              onChange={(e) => setGenNotes(e.target.value)}
              placeholder="Catatan tambahan untuk invoice ini..."
              rows={3}
            />
          </div>

          {genError && <div style={s.errorBanner}>{genError}</div>}

          <div style={s.modalActions}>
            <Button
              id="btn-modal-cancel"
              variant="secondary"
              onClick={() => setGenerateModal(false)}
              disabled={generateMutation.isPending}
            >
              Batal
            </Button>
            <Button
              id="btn-modal-confirm-generate"
              variant="primary"
              onClick={() => generateMutation.mutate()}
              disabled={generateMutation.isPending}
            >
              {generateMutation.isPending ? 'Memproses...' : 'Ya, Terbitkan Invoice'}
            </Button>
          </div>
        </div>
      </Modal>

      {/* ── Invoice Detail Modal ─────────────────────────────────────────── */}
      <Modal
        open={!!detailInvoice}
        title={`Detail Invoice — ${detailInvoice?.invoice_number ?? ''}`}
        onClose={() => setDetailInvoice(null)}
      >
        {detailInvoice && (
          <div style={s.modalBody}>
            <div style={s.detailGrid}>
              <div>
                <div style={s.detailLabel}>No. Invoice</div>
                <div style={s.detailValue}>{detailInvoice.invoice_number}</div>
              </div>
              <div>
                <div style={s.detailLabel}>Status</div>
                <Badge
                  label={getInvoiceStatusLabel(detailInvoice.status)}
                  color={getInvoiceStatusColor(detailInvoice.status)}
                />
              </div>
              <div>
                <div style={s.detailLabel}>Customer</div>
                <div style={s.detailValue}>{detailInvoice.customer_name || '—'}</div>
              </div>
              <div>
                <div style={s.detailLabel}>Tgl. Terbit</div>
                <div style={s.detailValue}>{formatDate(detailInvoice.issue_date)}</div>
              </div>
              <div>
                <div style={s.detailLabel}>Jatuh Tempo</div>
                <div style={s.detailValue}>{formatDate(detailInvoice.due_date)}</div>
              </div>
              <div>
                <div style={s.detailLabel}>Grand Total</div>
                <div style={{ ...s.detailValue, color: '#059669', fontWeight: 700 }}>
                  {formatRupiah(detailInvoice.grand_total)}
                </div>
              </div>
            </div>

            {detailInvoice.notes && (
              <div style={{ marginTop: '1rem' }}>
                <div style={s.detailLabel}>Catatan</div>
                <div style={s.detailNote}>{detailInvoice.notes}</div>
              </div>
            )}

            <div style={{ marginTop: '1.25rem' }}>
              <div style={s.taxCardTitle}>Rincian Pajak</div>
              <div style={s.taxRows}>
                <div style={s.taxRow}>
                  <span style={s.taxLabel}>Subtotal DPP</span>
                  <span style={s.taxValue}>{formatRupiah(detailInvoice.subtotal_dpp)}</span>
                </div>
                <div style={s.taxRow}>
                  <span style={s.taxLabel}>DPP Nilai Lain</span>
                  <span style={s.taxValue}>{formatRupiah(detailInvoice.dpp_nilai_lain)}</span>
                </div>
                <div style={s.taxRow}>
                  <span style={s.taxLabel}>PPN ({Number(detailInvoice.ppn_rate).toFixed(0)}%)</span>
                  <span style={{ ...s.taxValue, color: '#d97706' }}>{formatRupiah(detailInvoice.ppn_amount)}</span>
                </div>
                <div style={{ ...s.taxRow, borderTop: '1px solid #d1fae5', paddingTop: '0.5rem', marginTop: '0.25rem', fontWeight: 700 }}>
                  <span style={{ ...s.taxLabel, fontWeight: 700, color: '#111827' }}>Grand Total</span>
                  <span style={{ ...s.taxValue, color: '#059669', fontWeight: 700 }}>{formatRupiah(detailInvoice.grand_total)}</span>
                </div>
              </div>
            </div>

            <div style={s.modalActions}>
              <Button id="btn-close-detail" variant="secondary" onClick={() => setDetailInvoice(null)}>
                Tutup
              </Button>
            </div>
          </div>
        )}
      </Modal>

      {/* ── Payment Record Modal ─────────────────────────────────────────── */}
      <Modal
        open={paymentModal}
        title={`Catat Pembayaran — ${payingInvoice?.invoice_number ?? ''}`}
        onClose={() => setPaymentModal(false)}
      >
        {payingInvoice && (
          <div style={s.modalBody}>
            {/* Invoice Quick Summary Box */}
            <div style={s.paymentSummaryBox}>
              <div style={s.paymentSummaryRow}>
                <span style={s.paymentSummaryLabel}>Customer:</span>
                <span style={s.paymentSummaryValue}>{payingInvoice.customer_name || '—'}</span>
              </div>
              <div style={s.paymentSummaryRow}>
                <span style={s.paymentSummaryLabel}>Total Tagihan:</span>
                <span style={{ ...s.paymentSummaryValue, color: '#059669', fontWeight: 700, fontSize: '1rem' }}>
                  {formatRupiah(payingInvoice.grand_total)}
                </span>
              </div>
            </div>

            {/* Form Fields */}
            <div style={s.formGrid}>
              <div style={s.inputGroup}>
                <label style={s.inputLabel} htmlFor="pay-date">
                  Tanggal Pembayaran <span style={{ color: '#dc2626' }}>*</span>
                </label>
                <input
                  id="pay-date"
                  type="date"
                  style={s.inputField}
                  value={paymentForm.payment_date}
                  onChange={(e) => setPaymentForm((prev) => ({ ...prev, payment_date: e.target.value }))}
                />
              </div>

              <div style={s.inputGroup}>
                <label style={s.inputLabel} htmlFor="pay-method">
                  Metode Pembayaran <span style={{ color: '#dc2626' }}>*</span>
                </label>
                <select
                  id="pay-method"
                  style={s.selectField}
                  value={paymentForm.payment_method}
                  onChange={(e) => setPaymentForm((prev) => ({ ...prev, payment_method: e.target.value }))}
                >
                  <option value="Transfer BCA">Transfer BCA</option>
                  <option value="Transfer Mandiri">Transfer Mandiri</option>
                  <option value="Transfer BRI">Transfer BRI</option>
                  <option value="Transfer BNI">Transfer BNI</option>
                  <option value="Cash / Tunai">Cash / Tunai</option>
                  <option value="QRIS">QRIS</option>
                  <option value="Lainnya">Lainnya</option>
                </select>
              </div>

              <div style={s.inputGroup}>
                <label style={s.inputLabel} htmlFor="pay-amount">
                  Jumlah Bayar (Rp) <span style={{ color: '#dc2626' }}>*</span>
                </label>
                <input
                  id="pay-amount"
                  type="number"
                  style={s.inputField}
                  value={paymentForm.amount}
                  onChange={(e) => setPaymentForm((prev) => ({ ...prev, amount: e.target.value }))}
                  placeholder="Jumlah nominal pembayaran"
                />
              </div>

              <div style={s.inputGroup}>
                <label style={s.inputLabel} htmlFor="pay-ref">
                  No. Referensi / Bukti Transfer
                </label>
                <input
                  id="pay-ref"
                  type="text"
                  style={s.inputField}
                  value={paymentForm.reference_number}
                  onChange={(e) => setPaymentForm((prev) => ({ ...prev, reference_number: e.target.value }))}
                  placeholder="Contoh: TRX-982347234"
                />
              </div>

              <div style={{ ...s.inputGroup, gridColumn: '1 / -1' }}>
                <label style={s.inputLabel} htmlFor="pay-notes">
                  Catatan Pembayaran
                </label>
                <textarea
                  id="pay-notes"
                  style={s.notesTextarea}
                  rows={2}
                  value={paymentForm.notes}
                  onChange={(e) => setPaymentForm((prev) => ({ ...prev, notes: e.target.value }))}
                  placeholder="Catatan tambahan pembayaran (opsional)..."
                />
              </div>
            </div>

            {paymentError && <div style={s.errorBanner}>{paymentError}</div>}

            <div style={s.modalActions}>
              <Button
                id="btn-cancel-pay"
                variant="secondary"
                onClick={() => setPaymentModal(false)}
                disabled={paymentMutation.isPending}
              >
                Batal
              </Button>
              <Button
                id="btn-confirm-pay"
                variant="primary"
                onClick={() => paymentMutation.mutate()}
                disabled={paymentMutation.isPending}
              >
                {paymentMutation.isPending ? 'Menyimpan...' : 'Konfirmasi Lunas'}
              </Button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}

// ─── Styles ───────────────────────────────────────────────────────────────────

const s: Record<string, React.CSSProperties> = {
  container: {
    padding: '1.5rem 2rem',
    maxWidth: '1280px',
  },
  section: {
    marginBottom: '2rem',
  },
  sectionHeader: {
    display: 'flex',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    marginBottom: '0.75rem',
    gap: '1rem',
  },
  sectionTitle: {
    fontSize: '1rem',
    fontWeight: 700,
    color: '#111827',
    margin: 0,
  },
  sectionSub: {
    fontSize: '0.8rem',
    color: '#6b7280',
    margin: '0.25rem 0 0',
  },
  selectionBadge: {
    flexShrink: 0,
    display: 'flex',
    alignItems: 'center',
  },
  selectionChip: {
    background: '#2d5a3d',
    color: '#fff',
    borderRadius: '20px',
    padding: '0.25rem 0.875rem',
    fontSize: '0.8rem',
    fontWeight: 600,
  },
  table: {
    width: '100%',
    borderCollapse: 'collapse',
    fontSize: '0.875rem',
  },
  th: {
    padding: '0.625rem 0.875rem',
    textAlign: 'left',
    fontSize: '0.8rem',
    fontWeight: 600,
    color: '#374151',
    backgroundColor: '#f9fafb',
    borderBottom: '1px solid #e5e7eb',
    whiteSpace: 'nowrap',
  },
  tr: {
    borderBottom: '1px solid #f3f4f6',
    cursor: 'pointer',
    transition: 'background-color 0.1s',
  },
  trSelected: {
    backgroundColor: '#f0fdf4',
  },
  td: {
    padding: '0.625rem 0.875rem',
    verticalAlign: 'middle',
  },
  checkbox: {
    width: '16px',
    height: '16px',
    cursor: 'pointer',
    accentColor: '#2d5a3d',
  },
  customerName: {
    fontWeight: 600,
    color: '#111827',
    fontSize: '0.875rem',
  },
  subText: {
    fontSize: '0.75rem',
    color: '#6b7280',
    marginTop: '1px',
  },
  periodText: {
    fontSize: '0.8rem',
    color: '#374151',
  },
  descText: {
    fontSize: '0.8rem',
    color: '#374151',
    maxWidth: '220px',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
  taxYes: {
    background: '#dcfce7',
    color: '#15803d',
    borderRadius: '4px',
    padding: '2px 7px',
    fontSize: '0.7rem',
    fontWeight: 600,
  },
  taxNo: {
    background: '#f3f4f6',
    color: '#6b7280',
    borderRadius: '4px',
    padding: '2px 7px',
    fontSize: '0.7rem',
    fontWeight: 600,
  },
  loadingRow: {
    display: 'flex',
    alignItems: 'center',
    gap: '0.75rem',
    padding: '2rem',
    justifyContent: 'center',
  },
  loadingText: {
    color: '#6b7280',
    fontSize: '0.875rem',
  },
  warningBanner: {
    background: '#fffbeb',
    border: '1px solid #fcd34d',
    color: '#92400e',
    borderRadius: '8px',
    padding: '0.75rem 1rem',
    fontSize: '0.875rem',
    marginBottom: '0.75rem',
  },
  taxPanelGrid: {
    display: 'grid',
    gridTemplateColumns: '1fr 300px',
    gap: '1rem',
  },
  taxCardTitle: {
    fontWeight: 700,
    color: '#374151',
    fontSize: '0.875rem',
    marginBottom: '1rem',
  },
  taxRows: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
  },
  taxRow: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  taxLabel: {
    color: '#6b7280',
    fontSize: '0.875rem',
  },
  taxFormula: {
    fontSize: '0.75rem',
    color: '#9ca3af',
  },
  taxValue: {
    fontWeight: 600,
    color: '#111827',
    fontSize: '0.875rem',
  },
  errorText: {
    color: '#dc2626',
    fontSize: '0.875rem',
    padding: '0.5rem',
  },
  actionCardBody: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.75rem',
  },
  actionItemCount: {
    display: 'flex',
    alignItems: 'baseline',
    gap: '0.5rem',
  },
  actionItemNum: {
    fontSize: '2rem',
    fontWeight: 700,
    color: '#2d5a3d',
    lineHeight: 1,
  },
  actionItemLabel: {
    fontSize: '0.875rem',
    color: '#6b7280',
  },
  actionGrandTotal: {
    background: '#f0fdf4',
    borderRadius: '8px',
    padding: '0.75rem',
  },
  actionGtLabel: {
    fontSize: '0.75rem',
    color: '#6b7280',
    marginBottom: '0.25rem',
  },
  actionGtValue: {
    fontSize: '1.125rem',
    fontWeight: 700,
    color: '#059669',
  },
  actionWarning: {
    fontSize: '0.75rem',
    color: '#dc2626',
    margin: '0.25rem 0 0',
    textAlign: 'center',
  },
  invoiceNumber: {
    fontFamily: 'monospace',
    fontWeight: 600,
    color: '#2d5a3d',
    fontSize: '0.85rem',
  },
  actionBtn: {
    background: 'none',
    border: 'none',
    cursor: 'pointer',
    fontSize: '1rem',
    padding: '4px',
    borderRadius: '4px',
    transition: 'background 0.1s',
    marginLeft: '2px',
  },
  actionBtnPdf: {
    background: '#f0fdf4',
    border: '1px solid #bbf7d0',
    cursor: 'pointer',
    fontSize: '0.85rem',
    padding: '3px 7px',
    borderRadius: '4px',
    color: '#15803d',
    fontWeight: 600,
    transition: 'background 0.15s',
    marginLeft: '4px',
  },
  actionBtnDisabled: {
    opacity: 0.5,
    cursor: 'not-allowed',
  },
  actionBtnPay: {
    background: '#eff6ff',
    border: '1px solid #93c5fd',
    cursor: 'pointer',
    fontSize: '0.8rem',
    padding: '3px 7px',
    borderRadius: '4px',
    color: '#1d4ed8',
    fontWeight: 700,
    transition: 'background 0.15s',
    marginLeft: '4px',
    whiteSpace: 'nowrap' as const,
  },
  actionBtnReceipt: {
    background: '#fefce8',
    border: '1px solid #fde047',
    cursor: 'pointer',
    fontSize: '0.8rem',
    padding: '3px 7px',
    borderRadius: '4px',
    color: '#854d0e',
    fontWeight: 700,
    transition: 'background 0.15s',
    marginLeft: '4px',
    whiteSpace: 'nowrap' as const,
  },
  actionBtnWa: {
    background: '#f0fdf4',
    border: '1px solid #86efac',
    cursor: 'pointer',
    fontSize: '0.8rem',
    padding: '3px 7px',
    borderRadius: '4px',
    color: '#15803d',
    fontWeight: 700,
    transition: 'background 0.15s',
    marginLeft: '4px',
    whiteSpace: 'nowrap' as const,
  },
  // Modal styles
  modalBody: {
    padding: '0.25rem 0',
  },
  modalDesc: {
    fontSize: '0.9rem',
    color: '#374151',
    marginBottom: '1rem',
  },
  modalItemList: {
    border: '1px solid #e5e7eb',
    borderRadius: '8px',
    overflow: 'hidden',
    marginBottom: '1rem',
  },
  modalItem: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: '0.625rem 0.875rem',
    borderBottom: '1px solid #f3f4f6',
    fontSize: '0.85rem',
    gap: '1rem',
  },
  modalItemName: {
    color: '#374151',
    flex: 1,
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    whiteSpace: 'nowrap',
  },
  modalItemAmount: {
    fontWeight: 600,
    color: '#111827',
    whiteSpace: 'nowrap',
  },
  modalTaxSummary: {
    background: '#f9fafb',
    border: '1px solid #e5e7eb',
    borderRadius: '8px',
    padding: '0.875rem 1rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.5rem',
    marginBottom: '1rem',
    fontSize: '0.875rem',
  },
  modalTaxRow: {
    display: 'flex',
    justifyContent: 'space-between',
    color: '#374151',
  },
  notesGroup: {
    marginBottom: '1rem',
  },
  notesLabel: {
    display: 'block',
    fontSize: '0.875rem',
    fontWeight: 600,
    color: '#374151',
    marginBottom: '0.5rem',
  },
  notesTextarea: {
    width: '100%',
    padding: '0.625rem 0.75rem',
    border: '1px solid #d1d5db',
    borderRadius: '6px',
    fontSize: '0.875rem',
    resize: 'vertical',
    fontFamily: 'inherit',
    color: '#111827',
    outline: 'none',
    boxSizing: 'border-box',
  },
  errorBanner: {
    background: '#fef2f2',
    border: '1px solid #fecaca',
    color: '#dc2626',
    borderRadius: '6px',
    padding: '0.625rem 0.875rem',
    fontSize: '0.875rem',
    marginBottom: '0.75rem',
  },
  modalActions: {
    display: 'flex',
    justifyContent: 'flex-end',
    gap: '0.75rem',
    marginTop: '1.25rem',
  },
  // Detail modal
  detailGrid: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: '1rem',
  },
  detailLabel: {
    fontSize: '0.75rem',
    color: '#6b7280',
    fontWeight: 600,
    textTransform: 'uppercase',
    letterSpacing: '0.04em',
    marginBottom: '0.25rem',
  },
  detailValue: {
    fontSize: '0.9rem',
    color: '#111827',
    fontWeight: 600,
  },
  detailNote: {
    fontSize: '0.875rem',
    color: '#374151',
    background: '#f9fafb',
    padding: '0.625rem',
    borderRadius: '6px',
  },
  // Payment modal styles
  paymentSummaryBox: {
    background: '#f8fafc',
    border: '1px solid #e2e8f0',
    borderRadius: '8px',
    padding: '0.875rem 1rem',
    marginBottom: '1.25rem',
    display: 'flex',
    flexDirection: 'column',
    gap: '0.35rem',
  },
  paymentSummaryRow: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  paymentSummaryLabel: {
    fontSize: '0.85rem',
    color: '#64748b',
  },
  paymentSummaryValue: {
    fontSize: '0.9rem',
    color: '#0f172a',
    fontWeight: 600,
  },
  formGrid: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr',
    gap: '0.875rem',
    marginBottom: '1rem',
  },
  inputGroup: {
    display: 'flex',
    flexDirection: 'column',
    gap: '0.25rem',
  },
  inputLabel: {
    fontSize: '0.78rem',
    fontWeight: 600,
    color: '#374151',
  },
  inputField: {
    padding: '0.45rem 0.65rem',
    border: '1px solid #d1d5db',
    borderRadius: '6px',
    fontSize: '0.875rem',
    color: '#111827',
    outline: 'none',
    width: '100%',
    boxSizing: 'border-box',
  },
  selectField: {
    padding: '0.45rem 0.65rem',
    border: '1px solid #d1d5db',
    borderRadius: '6px',
    fontSize: '0.875rem',
    color: '#111827',
    outline: 'none',
    width: '100%',
    boxSizing: 'border-box',
    backgroundColor: '#ffffff',
  },
  exportExcelBtn: {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.375rem',
    background: '#ffffff',
    color: '#15803d',
    border: '1px solid #86efac',
    borderRadius: '6px',
    padding: '0.45rem 0.85rem',
    fontSize: '0.8125rem',
    fontWeight: 700,
    cursor: 'pointer',
    boxShadow: '0 1px 2px rgba(0,0,0,0.05)',
    transition: 'background 0.15s, border-color 0.15s',
  },
}
