import api from '@/lib/axios'

export const ownershipApi = {
  list: (params?: Record<string, unknown>) => api.get('/ownerships', { params }),
  get: (id: number) => api.get(`/ownerships/${id}`),
  create: <T extends object>(data: T) => api.post('/ownerships', data),
  update: <T extends object>(id: number, data: T) => api.put(`/ownerships/${id}`, data),
  delete: (id: number) => api.delete(`/ownerships/${id}`),
}
