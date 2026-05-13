import { api } from './api';

export const userService = {
  getLeaderboard: () => api.get('/user/leaderboard'),
  
  getFriends: () => api.get('/user/friends'),
  
  getFriendRequests: () => api.get('/user/friends/requests'),
  
  sendFriendRequest: (username: string) => api.post('/user/friends/request', { username }),
  
  acceptFriendRequest: (requestId: number) => api.post('/user/friends/accept', { request_id: requestId }),
  
  searchPlayers: (query: string) => api.get(`/user/search?q=${query}`),
};
