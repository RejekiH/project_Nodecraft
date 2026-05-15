import { api } from './api';

export const adminService = {
  healthCheck: () => api.get('/backup/health'),
  nodeStatus: () => api.get('/internal/backup/status'),
};