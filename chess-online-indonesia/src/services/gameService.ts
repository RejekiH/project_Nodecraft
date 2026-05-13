import { api } from './api';

export const gameService = {
  validateMove: (data: { room_id: string, from: string, to: string, promotion?: string }) => 
    api.post('/gameplay/validate-move', data),
  
  getState: (roomId: string) => 
    api.get(`/gameplay/state/${roomId}`),
  
  getMatchHistory: () => 
    api.get('/backup/history'),
  
  getMatchDetail: (id: string) => 
    api.get(`/backup/match/${id}`),
};
