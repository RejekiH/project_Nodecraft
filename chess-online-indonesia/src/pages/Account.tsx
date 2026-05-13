import React, { useState, useEffect } from 'react';
import Navbar from '../components/layout/Navbar';
import Sidebar from '../components/layout/Sidebar';
import { useAuth } from '../contexts/AuthContext';
import { User, Settings, Lock, Map, Sword, Award, Calendar, ChevronRight, LogOut, Save } from 'lucide-react';
import { api } from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { motion } from 'motion/react';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import Modal from '../components/ui/Modal';
import { Link, useNavigate } from 'react-router-dom';

const Account: React.FC = () => {
  const { user, logout, updateUser } = useAuth();
  const { showToast } = useNotification();
  const navigate = useNavigate();
  
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [history, setHistory] = useState<any[]>([]);
  const [editName, setEditName] = useState(user?.name || '');
  const [pwModalOpen, setPwModalOpen] = useState(false);
  const [savingProfile, setSavingProfile] = useState(false);

  const [pwData, setPwData] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const fetchData = async () => {
    try {
      const data = await api.get<{ matches: any[] }>('/backup/history');
      setHistory(data.matches);
    } catch (err) {
      console.error('Failed to fetch history', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingProfile(true);
    try {
      const data = await api.put<{ user: any }>('/user/profile', { name: editName });
      updateUser(data.user);
      showToast('Profil berhasil diperbarui!', 'success');
    } catch (err: any) {
      showToast(err.message || 'Gagal memperbarui profil', 'error');
    } finally {
      setSavingProfile(false);
    }
  };

  const handleUpdatePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (pwData.password !== pwData.password_confirmation) {
      showToast('Konfirmasi password tidak cocok', 'error');
      return;
    }
    try {
      await api.put('/user/password', pwData);
      showToast('Password berhasil diperbarui!', 'success');
      setPwModalOpen(false);
      setPwData({ current_password: '', password: '', password_confirmation: '' });
    } catch (err: any) {
      showToast(err.message || 'Gagal memperbarui password', 'error');
    }
  };

  const winRate = user ? Math.round((user.win || 0) / (user.total_match || 1) * 100) : 0;

  if (loading) return <LoadingSpinner />;

  return (
    <div className="flex flex-col min-h-screen bg-chess-bg text-chess-text">
      <Navbar sidebarOpen={sidebarOpen} setSidebarOpen={setSidebarOpen} />
      <div className="flex flex-1">
        <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        
        <main className="flex-1 p-4 sm:p-8 overflow-y-auto max-w-5xl mx-auto w-full">
          <motion.div initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} className="space-y-8">
            
            {/* Profile Header */}
            <div className="bg-chess-card border border-chess-border rounded-3xl p-8 flex flex-col md:flex-row items-center gap-8 relative overflow-hidden">
              <div className="absolute right-0 top-0 opacity-10 p-4">
                <Settings className="w-32 h-32 rotate-12" />
              </div>
              
              <div className="relative group">
                <div className="w-32 h-32 rounded-full bg-chess-green flex items-center justify-center font-black text-5xl shadow-2xl transition-transform group-hover:scale-105">
                  {user?.name.charAt(0).toUpperCase()}
                </div>
                <div className="absolute -bottom-2 -right-2 bg-chess-bg border border-chess-green p-2 rounded-full text-chess-green">
                  <Award className="w-6 h-6" />
                </div>
              </div>

              <div className="flex-1 text-center md:text-left z-10">
                <h1 className="text-3xl font-black mb-1">{user?.name}</h1>
                <p className="text-chess-muted font-bold mb-4">@{user?.username}</p>
                <div className="flex flex-wrap justify-center md:justify-start gap-3">
                  <div className="px-4 py-1.5 bg-white/5 border border-chess-border rounded-full text-xs font-bold flex items-center gap-2">
                    <Map className="w-3 h-3 text-chess-green" />
                    Peringkat: #24
                  </div>
                  <div className="px-4 py-1.5 bg-white/5 border border-chess-border rounded-full text-xs font-bold flex items-center gap-2">
                    <Calendar className="w-3 h-3 text-chess-green" />
                    Bergabung Mei 2024
                  </div>
                </div>
              </div>

              <div className="flex flex-col gap-2 w-full md:w-auto">
                <button 
                  onClick={() => setPwModalOpen(true)}
                  className="px-6 py-3 bg-white/5 hover:bg-white/10 rounded-xl font-bold flex items-center justify-center gap-3 transition-all"
                >
                  <Lock className="w-4 h-4" />
                  Ganti Password
                </button>
                <button 
                  onClick={() => { logout(); navigate('/login'); }}
                  className="px-6 py-3 text-red-400 hover:bg-red-500/10 rounded-xl font-bold flex items-center justify-center gap-3 transition-all"
                >
                  <LogOut className="w-4 h-4" />
                  Keluar
                </button>
              </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
              <StatItem label="Total Match" value={user?.total_match || 0} />
              <StatItem label="Menang" value={user?.win || 0} color="text-chess-green" />
              <StatItem label="Kalah" value={user?.loss || 0} color="text-red-400" />
              <StatItem label="Win Rate" value={`${winRate}%`} />
            </div>

            <div className="grid md:grid-cols-3 gap-8">
              {/* Profile Edit */}
              <div className="bg-chess-card border border-chess-border rounded-3xl p-6 h-fit">
                <h3 className="text-xl font-bold mb-6 flex items-center gap-2">
                  <User className="w-5 h-5 text-chess-green" />
                  Edit Profil
                </h3>
                <form onSubmit={handleUpdateProfile} className="space-y-4">
                  <div>
                    <label className="block text-xs font-black text-chess-muted uppercase mb-1">Nama Lengkap</label>
                    <input 
                      type="text" 
                      value={editName}
                      onChange={(e) => setEditName(e.target.value)}
                      className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green transition-all"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-black text-chess-muted uppercase mb-1">Username</label>
                    <input 
                      type="text" 
                      value={user?.username}
                      disabled
                      className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 opacity-50 cursor-not-allowed"
                    />
                  </div>
                  <button 
                    type="submit"
                    disabled={savingProfile || editName === user?.name}
                    className="w-full bg-chess-green hover:brightness-110 disabled:opacity-50 py-3 rounded-xl font-bold flex items-center justify-center gap-2 transition-all mt-4"
                  >
                    {savingProfile ? <div className="w-5 h-5 border-2 border-white/20 border-t-white rounded-full animate-spin" /> : <Save className="w-5 h-5" />}
                    SIMPAN PERUBAHAN
                  </button>
                </form>
              </div>

              {/* Match History */}
              <div className="md:col-span-2 space-y-4">
                <h3 className="text-xl font-bold flex items-center gap-2">
                  <Sword className="w-5 h-5 text-chess-green" />
                  Riwayat Pertandingan
                </h3>
                <div className="space-y-3">
                  {history.length > 0 ? history.map((match) => (
                    <Link 
                      key={match.id} 
                      to={`/game-result/${match.id}`}
                      className="flex items-center justify-between p-4 bg-chess-card border border-chess-border rounded-2xl hover:border-chess-green/30 transition-all group"
                    >
                      <div className="flex items-center gap-4">
                        <div className={`w-12 h-12 rounded-full border-2 flex items-center justify-center font-bold ${match.result === 'win' ? 'border-chess-green text-chess-green' : match.result === 'loss' ? 'border-red-500 text-red-500' : 'border-chess-muted text-chess-muted'}`}>
                          {match.opponent.charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <p className="font-bold">vs {match.opponent}</p>
                          <p className="text-xs text-chess-muted uppercase font-black tracking-widest">{match.date}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-6">
                        <div className="text-right hidden sm:block">
                          <p className="text-[10px] font-black text-chess-muted uppercase">Moves</p>
                          <p className="font-bold">{match.total_moves}</p>
                        </div>
                        <div className="text-right">
                          <p className={`text-xl font-black ${match.points_change > 0 ? 'text-chess-green' : match.points_change < 0 ? 'text-red-400' : 'text-chess-muted'}`}>
                            {match.points_change > 0 ? '+' : ''}{match.points_change}
                          </p>
                          <ChevronRight className="w-5 h-5 text-chess-muted group-hover:text-white transition-colors inline" />
                        </div>
                      </div>
                    </Link>
                  )) : (
                    <div className="p-12 text-center text-chess-muted bg-chess-card/50 rounded-3xl border border-dashed border-chess-border">
                      Belum ada pertandingan terlacak.
                    </div>
                  )}
                </div>
              </div>
            </div>

          </motion.div>
        </main>
      </div>

      {/* Password Modal */}
      <Modal isOpen={pwModalOpen} onClose={() => setPwModalOpen(false)} title="Ganti Password">
        <form onSubmit={handleUpdatePassword} className="space-y-4">
          <div>
            <label className="block text-sm font-bold mb-1 text-chess-muted">Password Lama</label>
            <input 
              type="password" 
              required
              value={pwData.current_password}
              onChange={(e) => setPwData({ ...pwData, current_password: e.target.value })}
              className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green"
            />
          </div>
          <div>
            <label className="block text-sm font-bold mb-1 text-chess-muted">Password Baru</label>
            <input 
              type="password" 
              required
              value={pwData.password}
              onChange={(e) => setPwData({ ...pwData, password: e.target.value })}
              className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green"
            />
          </div>
          <div>
            <label className="block text-sm font-bold mb-1 text-chess-muted">Konfirmasi Password Baru</label>
            <input 
              type="password" 
              required
              value={pwData.password_confirmation}
              onChange={(e) => setPwData({ ...pwData, password_confirmation: e.target.value })}
              className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green"
            />
          </div>
          <button 
            type="submit"
            className="w-full bg-chess-green hover:brightness-110 py-3 rounded-xl font-bold flex items-center justify-center gap-2 mt-4"
          >
            UPDATE PASSWORD
          </button>
        </form>
      </Modal>
    </div>
  );
};

const StatItem: React.FC<{ label: string, value: any, color?: string }> = ({ label, value, color }) => (
  <div className="bg-chess-card border border-chess-border p-4 rounded-2xl">
    <p className={`text-3xl font-black ${color || 'text-white'}`}>{value}</p>
    <p className="text-[10px] font-black text-chess-muted uppercase tracking-widest">{label}</p>
  </div>
);

export default Account;
