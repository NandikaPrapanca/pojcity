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

/**
 * Payload for the dedicated IPL generation endpoint.
 * The backend derives area and rate from the ownership record —
 * the frontend must NOT send rate or area.
 */
export interface IplGeneratePayload {
  ownership_id: number
  billing_period_start: string
  billing_period_end: string
  notes?: string | null
}

/**
 * Payload for the dedicated Water generation endpoint.
 * The backend loads the latest meter reading and derives progressive tier
 * costs + abonemen from the ownership's water_rate_group_id.
 * The frontend must NOT perform any calculation.
 */
export interface WaterGeneratePayload {
  ownership_id: number
  billing_period_start: string
  billing_period_end: string
  notes?: string | null
}

export const billingApi = {
  list: (params?: Record<string, unknown>) => api.get('/billing-items', { params }),
  get: (id: number) => api.get(`/billing-items/${id}`),
  create: (data: BillingItemPayload) => api.post('/billing-items', data),
  update: (id: number, data: Partial<BillingItemPayload>) => api.put(`/billing-items/${id}`, data),
  delete: (id: number) => api.delete(`/billing-items/${id}`),
  /** POST /billing/generate-ipl — authoritative IPL generation from ownership config */
  generateIpl: (data: IplGeneratePayload) => api.post('/billing/generate-ipl', data),
  /** POST /billing/generate-water — authoritative Water generation: progressive tiers + abonemen */
  generateWater: (data: WaterGeneratePayload) => api.post('/billing/generate-water', data),
}
