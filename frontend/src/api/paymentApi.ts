import api from '@/lib/axios'
import type { Invoice } from './invoiceApi'

export interface PaymentPayload {
  invoice_id: number
  amount: number
  payment_method: string
  payment_date: string
  reference_number?: string
  notes?: string
}

export interface Payment {
  id: number
  invoice_id: number
  amount: string | number
  payment_method: string
  payment_date: string
  reference_number?: string | null
  notes?: string | null
  created_at: string
  updated_at?: string | null
}

export const paymentApi = {
  /**
   * POST /api/v1/payments
   * Records payment and marks invoice as 'paid'.
   */
  create: (payload: PaymentPayload) =>
    api.post<{ success: boolean; data: { payment: Payment; invoice: Invoice }; message: string }>(
      '/payments',
      payload
    ),

  /**
   * GET /api/v1/payments/invoice/{invoiceId}
   * Retrieves payment transaction for an invoice.
   */
  getForInvoice: (invoiceId: number) =>
    api.get<{ success: boolean; data: Payment }>(`/payments/invoice/${invoiceId}`),
}
