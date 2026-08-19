import api from '@/lib/axios'
export const signatureApi = {
  list: (params?: Record<string, unknown>) => api.get('/signatures', { params }),
  get: (id: number) => api.get(`/signatures/${id}`),
  create: (data: FormData) => api.post('/signatures', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
  update: (id: number, data: FormData) => api.put(`/signatures/${id}`, data, { headers: { 'Content-Type': 'multipart/form-data' } }),
  delete: (id: number) => api.delete(`/signatures/${id}`),
}
