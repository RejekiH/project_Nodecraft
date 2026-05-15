import { api } from './api';

export const authService = {
  login: (credentials: { login: string; password: string }) =>
    api.post('/user/login', credentials),

  register: (data: {
    username: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => api.post('/user/register', data),

  logout: () => api.post('/user/logout'),

  refresh: (refresh_token: string) =>
    api.post('/user/refresh', { refresh_token }),

  getProfile: () => api.get('/user/profile'),

  updateProfile: (data: { email?: string }) =>
    api.put('/user/profile', data),

  updatePassword: (data: {
    current_password: string;
    new_password: string;
    new_password_confirmation: string;
  }) => api.put('/user/profile', data),
};