import api from '@/lib/axios'
export const hierarchyApi = {
  listClusters: (params?: Record<string, unknown>) => api.get('/clusters', { params }),
  getCluster: (id: number) => api.get(`/clusters/${id}`),
  createCluster: <T extends object>(data: T) => api.post('/clusters', data),
  updateCluster: <T extends object>(id: number, data: T) => api.put(`/clusters/${id}`, data),
  deleteCluster: (id: number) => api.delete(`/clusters/${id}`),
  clusterBlocks: (id: number) => api.get(`/clusters/${id}/blocks`),

  listBlocks: (params?: Record<string, unknown>) => api.get('/blocks', { params }),
  getBlock: (id: number) => api.get(`/blocks/${id}`),
  createBlock: <T extends object>(data: T) => api.post('/blocks', data),
  updateBlock: <T extends object>(id: number, data: T) => api.put(`/blocks/${id}`, data),
  deleteBlock: (id: number) => api.delete(`/blocks/${id}`),
  blockLots: (id: number) => api.get(`/blocks/${id}/lots`),

  listLots: (params?: Record<string, unknown>) => api.get('/lots', { params }),
  getLot: (id: number) => api.get(`/lots/${id}`),
  createLot: <T extends object>(data: T) => api.post('/lots', data),
  updateLot: <T extends object>(id: number, data: T) => api.put(`/lots/${id}`, data),
  deleteLot: (id: number) => api.delete(`/lots/${id}`),
}
