import api from '@/lib/axios'
export const taxApi = {
  list: () => api.get('/tax-configurations'),
  get: (id: number) => api.get(`/tax-configurations/${id}`),
  create: <T extends object>(data: T) => api.post('/tax-configurations', data),
  update: <T extends object>(id: number, data: T) => api.put(`/tax-configurations/${id}`, data),
  activate: (id: number) => api.put(`/tax-configurations/${id}/activate`),
}
