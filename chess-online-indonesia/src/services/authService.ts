import { api } from './api';

export const authService = {
  login: (credentials: any) => api.post('/user/login', credentials),
  register: (data: any) => api.post('/user/register', data),
  getProfile: () => api.get('/user/profile'),
  updateProfile: (data: any) => api.put('/user/profile', data),
  updatePassword: (data: any) => api.put('/user/password', data),
};
