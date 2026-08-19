import api from '@/lib/axios'
export const customerApi = {
  list: (params?: Record<string, unknown>) => api.get('/customers', { params }),
  get: (id: number) => api.get(`/customers/${id}`),
  create: <T extends object>(data: T) => api.post('/customers', data),
  update: <T extends object>(id: number, data: T) => api.put(`/customers/${id}`, data),
  delete: (id: number) => api.delete(`/customers/${id}`),
  listPics: (customerId: number) => api.get(`/customers/${customerId}/pics`),
  createPic: <T extends object>(customerId: number, data: T) => api.post(`/customers/${customerId}/pics`, data),
  updatePic: <T extends object>(customerId: number, picId: number, data: T) => api.put(`/customers/${customerId}/pics/${picId}`, data),
  deletePic: (customerId: number, picId: number) => api.delete(`/customers/${customerId}/pics/${picId}`),
}
