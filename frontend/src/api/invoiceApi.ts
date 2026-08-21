import api from '@/lib/axios'

// ─── Interfaces ───────────────────────────────────────────────────────────────

export interface InvoiceItem {
  id: number
  invoice_id: number
  billing_item_id: number
  billing_type: string
  billing_period_start: string
  billing_period_end: string
  description: string
  quantity: string | number
  unit: string
  unit_price: string | number
  subtotal: string | number
  management_fee_rate?: string | number | null
  management_fee_amount?: string | number | null
  pln_charge?: string | number | null
  apply_tax: number
  notes?: string | null
  created_at: string
}

export interface Invoice {
  id: number
  invoice_number: string
  company_id: number
  ownership_id: number
  customer_id: number
  created_by: number
  issue_date: string
  due_date: string
  subtotal_dpp: string | number
  dpp_nilai_lain: string | number
  ppn_amount: string | number
  grand_total: string | number
  tax_applied: number
  ppn_rate: string | number
  notes?: string | null
  status: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled'
  created_at: string
  updated_at: string
  // Relations
  company_name?: string | null
  customer_name?: string | null
  customer_type?: string | null
  customer_whatsapp?: string | null
  customer_email?: string | null
  project_name?: string | null
  cluster_name?: string | null
  block_name?: string | null
  lot_number?: string | null
  ownership_type?: string | null
  billing_address?: string | null
  items?: InvoiceItem[]
}

export interface TaxPreview {
  subtotal_taxable: number
  subtotal_nontaxable: number
  subtotal_dpp: number
  dpp_nilai_lain: number
  ppn_amount: number
  grand_total: number
  tax_applied: boolean
}

export interface GenerateInvoicePayload {
  billing_item_ids: number[]
  notes?: string
}

// ─── API ──────────────────────────────────────────────────────────────────────

export const invoiceApi = {
  /**
   * GET /api/v1/invoices
   * List all invoices with optional filters.
   */
  list: (params?: Record<string, unknown>) =>
    api.get<{ success: boolean; data: Invoice[] }>('/invoices', { params }),

  /**
   * GET /api/v1/invoices/{id}
   * Get a single invoice with all relations and line items.
   */
  get: (id: number) =>
    api.get<{ success: boolean; data: Invoice }>(`/invoices/${id}`),

  /**
   * POST /api/v1/invoices/preview-tax
   * Calculate tax for a set of billing items WITHOUT persisting.
   * NEVER calculate tax in React — always call this endpoint.
   */
  previewTax: (billingItemIds: number[]) =>
    api.post<{ success: boolean; data: TaxPreview }>('/invoices/preview-tax', {
      billing_item_ids: billingItemIds,
    }),

  /**
   * POST /api/v1/invoices/generate
   * Generate an invoice from draft billing items.
   * All items must belong to the same ownership.
   */
  generate: (payload: GenerateInvoicePayload) =>
    api.post<{ success: boolean; data: Invoice; message: string }>(
      '/invoices/generate',
      payload
    ),

  /**
   * GET /api/v1/invoices/{id}/pdf — returns the raw PDF binary.
   */
  downloadPdf: (id: number) =>
    api.get(`/invoices/${id}/pdf`, { responseType: 'blob' }),

  /**
   * GET /api/v1/invoices/{id}/receipt — returns the Kwitansi PDF binary.
   */
  downloadReceipt: (id: number) =>
    api.get(`/invoices/${id}/receipt`, { responseType: 'blob' }),

  /**
   * POST /api/v1/invoices/{id}/send-whatsapp
   * Mock WhatsApp delivery — logs the attempt and simulates a 2-second delay.
   */
  sendWhatsApp: (id: number) =>
    api.post<{ success: boolean; data: { log: object; phone: string; message: string }; message: string }>(
      `/invoices/${id}/send-whatsapp`
    ),
}

/**
 * Fetches the invoice PDF blob via Axios and opens it in a new browser tab.
 */
export async function openInvoicePdf(id: number): Promise<void> {
  try {
    const res = await api.get(`/invoices/${id}/pdf`, { responseType: 'blob' })
    const blob = new Blob([res.data as BlobPart], { type: 'application/pdf' })
    const url  = URL.createObjectURL(blob)
    const win  = window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
    if (!win) {
      const a = document.createElement('a')
      a.href = url
      a.download = `Invoice-${id}.pdf`
      a.click()
    }
  } catch {
    throw new Error('Gagal memuat PDF invoice.')
  }
}

/**
 * Fetches the Kwitansi (Receipt) PDF blob via Axios and opens it in a new browser tab.
 */
export async function openInvoiceReceiptPdf(id: number): Promise<void> {
  try {
    const res = await api.get(`/invoices/${id}/receipt`, { responseType: 'blob' })
    const blob = new Blob([res.data as BlobPart], { type: 'application/pdf' })
    const url  = URL.createObjectURL(blob)
    const win  = window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
    if (!win) {
      const a = document.createElement('a')
      a.href = url
      a.download = `Kwitansi-${id}.pdf`
      a.click()
    }
  } catch {
    throw new Error('Gagal memuat PDF kwitansi.')
  }
}
