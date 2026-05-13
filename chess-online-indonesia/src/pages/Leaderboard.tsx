import React, { useState, useEffect } from 'react';
import Navbar from '../components/layout/Navbar';
import Sidebar from '../components/layout/Sidebar';
import { Trophy, Search, ChevronUp, ChevronDown, User, Medal } from 'lucide-react';
import { api } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import { motion } from 'motion/react';
import LoadingSpinner from '../components/ui/LoadingSpinner';

const Leaderboard: React.FC = () => {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [players, setPlayers] = useState<any[]>([]);
  const [search, setSearch] = useState('');
  const { user: currentUser } = useAuth();

  const fetchLeaderboard = async () => {
    try {
      const data = await api.get<{ players: any[] }>('/user/leaderboard');
      setPlayers(data.players);
    } catch (err) {
      console.error('Failed to fetch leaderboard', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLeaderboard();
  }, []);

  const filteredPlayers = players.filter(p => 
    p.username.toLowerCase().includes(search.toLowerCase()) || 
    p.name.toLowerCase().includes(search.toLowerCase())
  );

  const topThree = players.slice(0, 3);
  const others = filteredPlayers.slice(3);

  if (loading) return <LoadingSpinner />;

  return (
    <div className="flex flex-col min-h-screen bg-chess-bg text-chess-text">
      <Navbar sidebarOpen={sidebarOpen} setSidebarOpen={setSidebarOpen} />
      <div className="flex flex-1">
        <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        
        <main className="flex-1 p-4 sm:p-8 overflow-y-auto max-w-7xl mx-auto w-full">
          <div className="text-center mb-12">
            <h1 className="text-5xl font-black tracking-tighter mb-4 italic uppercase">Papan Peringkat</h1>
            <p className="text-chess-muted max-w-md mx-auto">Para pemain terbaik di ekosistem CHESSID. Apakah Anda salah satunya?</p>
          </div>

          {/* Top 3 Podium */}
          <div className="grid grid-cols-3 gap-2 sm:gap-4 max-w-4xl mx-auto items-end mb-16 px-2">
            {/* Rank 2 */}
            {topThree[1] && (
              <div className="flex flex-col items-center">
                <PodiumAvatar player={topThree[1]} rank={2} color="bg-slate-400" />
                <div className="w-full bg-slate-400/20 border-x-4 border-t-4 border-slate-400 h-24 sm:h-32 rounded-t-2xl flex flex-col items-center justify-center p-2 text-center">
                  <span className="text-xl sm:text-2xl font-black text-slate-400">#2</span>
                </div>
              </div>
            )}

            {/* Rank 1 */}
            {topThree[0] && (
              <div className="flex flex-col items-center z-10 scale-110 sm:scale-125">
                <div className="mb-2 animate-bounce">
                  <Medal className="w-10 h-10 text-yellow-400" />
                </div>
                <PodiumAvatar player={topThree[0]} rank={1} color="bg-yellow-500" highlight />
                <div className="w-full bg-yellow-500/20 border-x-4 border-t-4 border-yellow-500 h-32 sm:h-48 rounded-t-2xl flex flex-col items-center justify-center p-2 text-center shadow-[0_-15px_30px_rgba(234,179,8,0.2)]">
                  <span className="text-2xl sm:text-3xl font-black text-yellow-500">#1</span>
                </div>
              </div>
            )}

            {/* Rank 3 */}
            {topThree[2] && (
              <div className="flex flex-col items-center">
                <PodiumAvatar player={topThree[2]} rank={3} color="bg-amber-700" />
                <div className="w-full bg-amber-700/20 border-x-4 border-t-4 border-amber-700 h-16 sm:h-24 rounded-t-2xl flex flex-col items-center justify-center p-2 text-center">
                  <span className="text-xl sm:text-2xl font-black text-amber-700">#3</span>
                </div>
              </div>
            )}
          </div>

          {/* Search & Filters */}
          <div className="mb-8 flex flex-col sm:flex-row gap-4 max-w-5xl mx-auto">
            <div className="flex-1 relative group">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-chess-muted group-focus-within:text-chess-green transition-colors" />
              <input 
                type="text" 
                placeholder="Cari pemain berdasarkan username atau nama..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full bg-chess-card border border-chess-border rounded-2xl py-4 pl-12 pr-4 outline-none focus:border-chess-green transition-all"
              />
            </div>
            <div className="flex gap-2">
              {['Semua', 'Bulan Ini', 'Minggu Ini'].map((tab) => (
                <button key={tab} className={`px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider transition-all ${tab === 'Semua' ? 'bg-chess-green text-white' : 'bg-white/5 text-chess-muted hover:bg-white/10'}`}>
                  {tab}
                </button>
              ))}
            </div>
          </div>

          {/* Table */}
          <div className="bg-chess-card border border-chess-border rounded-3xl overflow-hidden max-w-5xl mx-auto">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="text-left text-chess-muted text-xs font-black uppercase tracking-widest border-b border-chess-border">
                    <th className="px-6 py-5">Rank</th>
                    <th className="px-6 py-5">Pemain</th>
                    <th className="px-6 py-5 text-center">Poin</th>
                    <th className="px-6 py-5 text-center">Menang</th>
                    <th className="px-6 py-5 text-center">Kalah</th>
                    <th className="px-6 py-5 text-center">Win Rate</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-chess-border">
                  {others.map((player) => (
                    <tr 
                      key={player.id} 
                      className={`hover:bg-white/5 transition-colors ${player.id === currentUser?.id ? 'bg-chess-green/10' : ''}`}
                    >
                      <td className="px-6 py-4 font-black text-lg italic text-chess-muted">#{player.rank}</td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className={`w-10 h-10 rounded-full flex items-center justify-center font-bold border-2 ${player.id === currentUser?.id ? 'border-chess-green' : 'border-chess-border bg-chess-bg'}`}>
                            {player.username.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <p className="font-bold flex items-center gap-2">
                              {player.username}
                              {player.id === currentUser?.id && <span className="text-[10px] bg-chess-green px-1.5 rounded text-white italic">YOU</span>}
                            </p>
                            <p className="text-xs text-chess-muted">{player.name}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-center font-black text-chess-green">{player.points}</td>
                      <td className="px-6 py-4 text-center text-sm font-bold">{player.win}</td>
                      <td className="px-6 py-4 text-center text-sm font-bold text-red-400">{player.loss}</td>
                      <td className="px-6 py-4 text-center">
                        <div className="inline-flex items-center flex-col">
                          <span className="text-sm font-black">{Math.round((player.win / (player.win + player.loss || 1)) * 100)}%</span>
                          <div className="w-12 h-1 bg-white/5 rounded-full mt-1">
                            <div className="h-full bg-chess-green rounded-full" style={{ width: `${(player.win / (player.win + player.loss || 1)) * 100}%` }} />
                          </div>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {filteredPlayers.length === 0 && (
              <div className="p-20 text-center text-chess-muted">
                Tidak ada pemain yang ditemukan.
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  );
};

const PodiumAvatar: React.FC<{ player: any, rank: number, color: string, highlight?: boolean }> = ({ player, color, highlight }) => (
  <div className="flex flex-col items-center mb-4">
    <div className={`w-16 h-16 sm:w-24 sm:h-24 rounded-full border-4 flex items-center justify-center font-black text-2xl sm:text-4xl shadow-2xl relative mb-4 ${highlight ? 'border-yellow-400 animate-pulse' : 'border-chess-border bg-chess-bg'}`}>
      {player.username.charAt(0).toUpperCase()}
      {highlight && <div className="absolute -top-6 text-2xl">👑</div>}
    </div>
    <div className="text-center">
      <p className="font-black text-sm sm:text-base tracking-tight truncate max-w-[100px] sm:max-w-[140px]">{player.username}</p>
      <p className={`font-bold text-xs ${highlight ? 'text-yellow-500' : 'text-chess-muted'}`}>{player.points} pts</p>
    </div>
  </div>
);

export default Leaderboard;
