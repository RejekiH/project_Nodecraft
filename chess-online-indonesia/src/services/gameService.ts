import { api } from './api';

export const gameService = {
  getMatchHistory: (userId: string) =>
    api.get(`/room/history/${userId}`),

  getRoomDetail: (id: string) =>
    api.get(`/room/${id}`),
};