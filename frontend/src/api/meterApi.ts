import api from '@/lib/axios'

export const meterApi = {
  list: (params?: Record<string, unknown>) => api.get('/meter-readings', { params }),
  get: (id: number) => api.get(`/meter-readings/${id}`),
  create: (data: FormData | Record<string, unknown>) =>
    api.post('/meter-readings', data, data instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined),
  update: (id: number, data: FormData | Record<string, unknown>) =>
    api.post(`/meter-readings/${id}`, data, data instanceof FormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined),
  delete: (id: number) => api.delete(`/meter-readings/${id}`),
  forOwnership: (ownershipId: number) => api.get(`/ownerships/${ownershipId}/meter-readings`),
  latest: (ownershipId: number) => api.get(`/ownerships/${ownershipId}/meter-readings/latest`),
}
