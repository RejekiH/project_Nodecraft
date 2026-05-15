import { api } from './api';

export const roomService = {
  create: (time_control: string) =>
    api.post('/room', { time_control }),

  join: (code: string) =>
    api.post('/room/join', { code }),

  getById: (id: string) =>
    api.get(`/room/${id}`),

  getByCode: (code: string) =>
    api.get(`/room/code/${code}`),

  list: (params?: { limit?: number; offset?: number; time_control?: string }) => {
    const query = new URLSearchParams();
    if (params?.limit) query.set('limit', String(params.limit));
    if (params?.offset) query.set('offset', String(params.offset));
    if (params?.time_control) query.set('time_control', params.time_control);
    const qs = query.toString();
    return api.get(`/room${qs ? '?' + qs : ''}`);
  },

  cancel: (id: string) =>
    api.delete(`/room/${id}`),

  getHistory: (userId: string) =>
    api.get(`/room/history/${userId}`),
};