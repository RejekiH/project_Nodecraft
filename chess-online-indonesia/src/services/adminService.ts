import { api } from './api';

export const adminService = {
  getHardware: () => api.get('/admin/hardware'),
  
  getPlayers: () => api.get('/admin/players'),
  
  updatePlayerStatus: (id: number, status: string) => 
    api.put(`/admin/players/${id}/status`, { status }),
  
  getActiveMatches: () => 
    api.get('/admin/matches/active'),
  
  forceEndMatch: (id: number) => 
    api.post(`/admin/matches/${id}/force-end`),
  
  getSavedMatches: () => 
    api.get('/admin/matches/saved'),
  
  getNotifications: () => 
    api.get('/admin/notifications'),
  
  resolveNotification: (id: number) => 
    api.put(`/admin/notifications/${id}/resolve`),
  
  getAdminAccount: () => 
    api.get('/admin/account'),
  
  updateAdminAccount: (data: any) => 
    api.put('/admin/account', data),
};
