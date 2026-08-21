import api from '@/lib/axios'

export const reportApi = {
  /**
   * GET /api/v1/reports/export-invoices
   * Fetches the generated Excel spreadsheet blob.
   */
  exportInvoicesExcel: () =>
    api.get('/reports/export-invoices', { responseType: 'blob' }),
}

/**
 * Triggers the browser download of the Excel invoice report.
 */
export async function downloadInvoicesExcel(): Promise<void> {
  try {
    const res = await reportApi.exportInvoicesExcel()
    const blob = new Blob([res.data as BlobPart], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `Rekap_Invoice_${new Date().toISOString().slice(0, 10)}.xlsx`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 10_000)
  } catch {
    throw new Error('Gagal mengunduh file Excel.')
  }
}
