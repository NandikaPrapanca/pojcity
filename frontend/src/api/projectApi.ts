import api from '@/lib/axios'
export const projectApi = {
  list: (params?: Record<string, unknown>) => api.get('/projects', { params }),
  get: (id: number) => api.get(`/projects/${id}`),
  create: <T extends object>(data: T) => api.post('/projects', data),
  update: <T extends object>(id: number, data: T) => api.put(`/projects/${id}`, data),
  delete: (id: number) => api.delete(`/projects/${id}`),
  clusters: (id: number) => api.get(`/projects/${id}/clusters`),
}
