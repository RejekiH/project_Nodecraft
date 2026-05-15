import { api } from './api';

export const userService = {
  getLeaderboard: (params?: { limit?: number; offset?: number }) => {
    const query = new URLSearchParams();
    if (params?.limit) query.set('limit', String(params.limit));
    if (params?.offset) query.set('offset', String(params.offset));
    const qs = query.toString();
    return api.get(`/user/leaderboard${qs ? '?' + qs : ''}`);
  },

  getPublicProfile: (username: string) =>
    api.get(`/user/${username}`),
};