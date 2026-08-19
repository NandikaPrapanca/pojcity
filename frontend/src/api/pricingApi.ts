import api from '@/lib/axios'
export const pricingApi = {
  listIplRates: (params?: Record<string, unknown>) => api.get('/ipl-rates', { params }),
  getIplRate: (id: number) => api.get(`/ipl-rates/${id}`),
  createIplRate: <T extends object>(data: T) => api.post('/ipl-rates', data),
  updateIplRate: <T extends object>(id: number, data: T) => api.put(`/ipl-rates/${id}`, data),
  deleteIplRate: (id: number) => api.delete(`/ipl-rates/${id}`),

  listWaterGroups: (params?: Record<string, unknown>) => api.get('/water-rate-groups', { params }),
  getWaterGroup: (id: number) => api.get(`/water-rate-groups/${id}`),
  createWaterGroup: <T extends object>(data: T) => api.post('/water-rate-groups', data),
  updateWaterGroup: <T extends object>(id: number, data: T) => api.put(`/water-rate-groups/${id}`, data),
  deleteWaterGroup: (id: number) => api.delete(`/water-rate-groups/${id}`),
  groupTiers: (id: number) => api.get(`/water-rate-groups/${id}/tiers`),
  createTier: <T extends object>(groupId: number, data: T) => api.post(`/water-rate-groups/${groupId}/tiers`, data),
  updateTier: <T extends object>(id: number, data: T) => api.put(`/water-rate-tiers/${id}`, data),
  deleteTier: (id: number) => api.delete(`/water-rate-tiers/${id}`),
}
