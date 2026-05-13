import { api } from './api';

export const roomService = {
  create: (timeControl?: string) => api.post('/room/create', { time_control: timeControl }),
  join: (roomCode: string) => api.post('/room/join', { room_code: roomCode }),
  getStatus: (id: string) => api.get(`/room/status/${id}`),
  list: () => api.get('/room/list'),
};
