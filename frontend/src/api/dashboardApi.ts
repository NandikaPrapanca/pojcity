import api from '@/lib/axios'

export interface DashboardInvoiceMetrics {
  total: number
  draft: number
  sent: number
  paid: number
  overdue: number
  outstanding_amount: number
  total_invoiced: number
  total_paid: number
}

export interface DashboardBillingMetrics {
  draft_count: number
  invoiced_count: number
  draft_subtotal: number
}

export interface DashboardWhatsAppMetrics {
  total_sent: number
}

export interface DashboardMasterMetrics {
  customers: number
  ownerships: number
  projects: number
}

export interface MonthlyTrend {
  period: string
  label: string
  paid_revenue: number
  outstanding_revenue: number
  total_invoiced: number
  count_invoices: number
}

export interface DashboardSummary {
  invoices: DashboardInvoiceMetrics
  billing_items: DashboardBillingMetrics
  whatsapp: DashboardWhatsAppMetrics
  master: DashboardMasterMetrics
  monthly_trends?: MonthlyTrend[]
  generated_at: string
}

export const dashboardApi = {
  /**
   * GET /api/v1/dashboard/summary
   * Returns aggregated operational metrics — all calculated server-side.
   */
  getSummary: () =>
    api.get<{ success: boolean; data: DashboardSummary }>('/dashboard/summary'),
}
