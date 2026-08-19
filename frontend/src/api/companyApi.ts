import api from '@/lib/axios'
export const companyApi = {
  list: () => api.get('/companies'),
  get: (id: number) => api.get(`/companies/${id}`),
  create: <T extends object>(data: T) => api.post('/companies', data),
  update: <T extends object>(id: number, data: T) => api.put(`/companies/${id}`, data),
  delete: (id: number) => api.delete(`/companies/${id}`),
}
