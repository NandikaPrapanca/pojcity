import api from '@/lib/axios'

export interface BillingItemPayload {
  ownership_id: number | string
  billing_type: 'ipl' | 'water' | 'electricity' | 'other'
  billing_period_start: string
  billing_period_end: string
  meter_reading_id?: number | string | null
  description: string
  quantity: number | string
  unit: string
  unit_price: number | string
  apply_tax?: number | boolean
  notes?: string | null
  status?: 'draft' | 'invoiced' | 'cancelled'
}

export const billingApi = {
  list: (params?: Record<string, unknown>) => api.get('/billing-items', { params }),
  get: (id: number) => api.get(`/billing-items/${id}`),
  create: (data: BillingItemPayload) => api.post('/billing-items', data),
  update: (id: number, data: Partial<BillingItemPayload>) => api.put(`/billing-items/${id}`, data),
  delete: (id: number) => api.delete(`/billing-items/${id}`),
}
