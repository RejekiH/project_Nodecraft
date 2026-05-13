import React, { useState, useEffect } from 'react';
import Navbar from '../components/layout/Navbar';
import Sidebar from '../components/layout/Sidebar';
import { useAuth } from '../contexts/AuthContext';
import { QUOTES } from '../utils/constants';
import { Play, Trophy, Users, Award, Sword, History, ChevronRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api } from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { motion } from 'motion/react';
import LoadingSpinner from '../components/ui/LoadingSpinner';

const Home: React.FC = () => {
  const { user } = useAuth();
  const { showToast } = useNotification();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<any>(null);
  const [recentMatches, setRecentMatches] = useState<any[]>([]);

  const todayQuote = QUOTES[new Date().getDate() % QUOTES.length];

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [profileData, historyData] = await Promise.all([
          api.get<{ user: any }>('/user/profile'),
          api.get<{ matches: any[] }>('/backup/history')
        ]);
        setStats(profileData.user);
        setRecentMatches(historyData.matches.slice(0, 3));
      } catch (err) {
        console.error('Error fetching home data', err);
        // showToast('Gagal memuat data statistik', 'error');
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, []);

  if (loading) return <LoadingSpinner />;

  return (
    <div className="flex flex-col min-h-screen bg-chess-bg text-chess-text">
      <Navbar sidebarOpen={sidebarOpen} setSidebarOpen={setSidebarOpen} />
      
      <div className="flex flex-1">
        <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        
        <main className="flex-1 p-4 sm:p-8 overflow-y-auto max-w-7xl mx-auto w-full">
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="space-y-8">
            
            {/* Hero Card */}
            <div className="relative bg-chess-card border border-chess-border rounded-3xl overflow-hidden p-8 sm:p-12 mb-8 group">
              <div className="absolute right-0 top-0 w-1/3 h-full opacity-10 pointer-events-none transform translate-x-1/4 select-none">
                <span className="text-[300px]">♞</span>
              </div>
              <div className="relative z-10 max-w-xl">
                <h2 className="text-4xl sm:text-5xl font-black italic tracking-tighter mb-4 leading-tight">
                  SIAP UNTUK <span className="text-chess-green">SKAK MAT?</span>
                </h2>
                <p className="text-chess-muted text-lg mb-8">
                  Tantang pemain dari seluruh Indonesia dan tingkatkan rating Anda di CHESSID.
                </p>
                <Link 
                  to="/room" 
                  className="inline-flex items-center gap-3 bg-chess-green hover:brightness-110 px-8 py-4 rounded-2xl font-black text-xl shadow-lg shadow-chess-green/20 transition-all hover:scale-105 active:scale-95"
                >
                  <Sword className="w-6 h-6" />
                  MAINKAN SEKARANG
                </Link>
              </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
              <StatCard label="Total Match" value={stats?.total_match || 0} icon={Play} />
              <StatCard label="Menang" value={stats?.win || 0} icon={Trophy} color="text-chess-green" />
              <StatCard label="Kalah" value={stats?.loss || 0} icon={Award} color="text-red-400" />
              <StatCard label="Poin" value={stats?.points || 0} icon={Users} color="text-yellow-500" />
            </div>

            <div className="grid lg:grid-cols-2 gap-8">
              {/* Recent Matches */}
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h3 className="text-xl font-bold flex items-center gap-2">
                    <History className="w-5 h-5 text-chess-green" />
                    Match Terakhir
                  </h3>
                  <Link to="/account" className="text-sm text-chess-muted hover:text-chess-green flex items-center">
                    Lihat Semua <ChevronRight className="w-4 h-4" />
                  </Link>
                </div>
                
                <div className="space-y-3">
                  {recentMatches.length > 0 ? (
                    recentMatches.map((match) => (
                      <Link 
                        key={match.id} 
                        to={`/game-result/${match.id}`}
                        className="flex items-center justify-between p-4 bg-chess-card border border-chess-border rounded-xl hover:bg-white/5 transition-all group"
                      >
                        <div className="flex items-center gap-4">
                          <div className={`w-2 h-10 rounded-full ${match.result === 'win' ? 'bg-chess-green' : match.result === 'loss' ? 'bg-red-500' : 'bg-chess-muted'}`} />
                          <div>
                            <p className="font-bold">vs {match.opponent}</p>
                            <p className="text-xs text-chess-muted">{match.date}</p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className={`font-black ${match.points_change > 0 ? 'text-chess-green' : match.points_change < 0 ? 'text-red-400' : 'text-chess-muted'}`}>
                            {match.points_change > 0 ? '+' : ''}{match.points_change}
                          </p>
                          <p className="text-[10px] uppercase font-bold text-chess-muted">{match.result}</p>
                        </div>
                      </Link>
                    ))
                  ) : (
                    <div className="p-12 text-center text-chess-muted bg-chess-card/50 rounded-xl border border-dashed border-chess-border">
                      Belum ada riwayat pertandingan
                    </div>
                  )}
                </div>
              </div>

              {/* Daily Quote & Motivation */}
              <div className="space-y-4">
                <h3 className="text-xl font-bold">Inspirasi Hari Ini</h3>
                <div className="p-8 bg-chess-green/5 border border-chess-green/20 rounded-3xl relative overflow-hidden italic">
                  <span className="absolute -top-4 -left-2 text-8xl text-chess-green/10 select-none">"</span>
                  <p className="text-xl text-chess-green leading-relaxed text-center relative z-10">
                    {todayQuote}
                  </p>
                </div>
              </div>
            </div>

          </motion.div>
        </main>
      </div>
    </div>
  );
};

const StatCard: React.FC<{ label: string, value: number, icon: any, color?: string }> = ({ label, value, icon: Icon, color }) => (
  <div className="bg-chess-card border border-chess-border p-6 rounded-2xl flex flex-col justify-between group hover:border-chess-green/30 transition-all">
    <div className={`w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform ${color || 'text-chess-muted'}`}>
      <Icon className="w-6 h-6" />
    </div>
    <div>
      <p className="text-4xl font-black tracking-tight">{value}</p>
      <p className="text-sm font-bold text-chess-muted uppercase tracking-wider">{label}</p>
    </div>
  </div>
);

export default Home;
