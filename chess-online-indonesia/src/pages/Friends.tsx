import React, { useState, useEffect } from 'react';
import Navbar from '../components/layout/Navbar';
import Sidebar from '../components/layout/Sidebar';
import { Users, UserPlus, UserMinus, Search, Mail, Check, X, Play } from 'lucide-react';
import { api } from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { motion, AnimatePresence } from 'motion/react';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { useNavigate } from 'react-router-dom';

const Friends: React.FC = () => {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'friends' | 'requests'>('friends');
  const [friends, setFriends] = useState<any[]>([]);
  const [requests, setRequests] = useState<{ incoming: any[], outgoing: any[] }>({ incoming: [], outgoing: [] });
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [searching, setSearching] = useState(false);

  const { showToast } = useNotification();
  const navigate = useNavigate();

  const fetchFriends = async () => {
    try {
      const [friendsData, requestsData] = await Promise.all([
        api.get<{ friends: any[] }>('/user/friends'),
        api.get<{ incoming: any[], outgoing: any[] }>('/user/friends/requests')
      ]);
      setFriends(friendsData.friends);
      setRequests(requestsData);
    } catch (err) {
      console.error('Failed to fetch friends data', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFriends();
  }, []);

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!searchQuery) return;
    setSearching(true);
    try {
      const data = await api.get<{ players: any[] }>(`/user/search?q=${searchQuery}`);
      setSearchResults(data.players);
    } catch (err) {
      showToast('Gagal mencari pemain', 'error');
    } finally {
      setSearching(false);
    }
  };

  const sendFriendRequest = async (username: string) => {
    try {
      await api.post('/user/friends/request', { username });
      showToast('Permintaan pertemanan dikirim!', 'success');
      fetchFriends();
    } catch (err: any) {
      showToast(err.message || 'Gagal mengirim permintaan', 'error');
    }
  };

  const acceptRequest = async (requestId: number) => {
    try {
      await api.post('/user/friends/accept', { request_id: requestId });
      showToast('Permintaan diterima!', 'success');
      fetchFriends();
    } catch (err: any) {
      showToast(err.message || 'Gagal menerima permintaan', 'error');
    }
  };

  const createChallenge = async (friendId: number) => {
    try {
      const data = await api.post<{ room: any }>('/room/create');
      showToast('Tantangan dibuat! Menunggu teman...', 'success');
      navigate(`/game/${data.room.id}`);
      // In a real app, you'd send an invite signal via WS or store it
    } catch (err: any) {
      showToast(err.message || 'Gagal membuat tantangan', 'error');
    }
  };

  if (loading) return <LoadingSpinner />;

  return (
    <div className="flex flex-col min-h-screen bg-chess-bg text-chess-text">
      <Navbar sidebarOpen={sidebarOpen} setSidebarOpen={setSidebarOpen} />
      <div className="flex flex-1">
        <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        
        <main className="flex-1 p-4 sm:p-8 overflow-y-auto max-w-4xl mx-auto w-full">
          <div className="mb-10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <h1 className="text-4xl font-black italic tracking-tighter uppercase">Komunitas Teman</h1>
            <div className="flex bg-chess-card border border-chess-border p-1 rounded-xl">
              <button 
                onClick={() => setActiveTab('friends')}
                className={`px-6 py-2 rounded-lg text-sm font-bold transition-all ${activeTab === 'friends' ? 'bg-chess-green text-white shadow-lg shadow-chess-green/20' : 'text-chess-muted hover:bg-white/5'}`}
              >
                Teman ({friends.length})
              </button>
              <button 
                onClick={() => setActiveTab('requests')}
                className={`px-6 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 ${activeTab === 'requests' ? 'bg-chess-green text-white shadow-lg shadow-chess-green/20' : 'text-chess-muted hover:bg-white/5'}`}
              >
                Permintaan
                {requests.incoming.length > 0 && <span className="w-5 h-5 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center animate-pulse">{requests.incoming.length}</span>}
              </button>
            </div>
          </div>

          <div className="space-y-8">
            {/* Search Bar */}
            <form onSubmit={handleSearch} className="relative group">
              <input 
                type="text" 
                placeholder="Cari teman berdasarkan username..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full bg-chess-card border border-chess-border rounded-2xl py-4 pl-12 pr-4 outline-none focus:border-chess-green transition-all"
              />
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-chess-muted group-focus-within:text-chess-green" />
              <button type="submit" className="absolute right-3 top-1/2 -translate-y-1/2 bg-chess-green px-4 py-1.5 rounded-lg text-xs font-bold hover:brightness-110 transition-all">
                CARI
              </button>
            </form>

            <AnimatePresence mode="wait">
              {searchResults.length > 0 && (
                <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }} className="space-y-3">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xs font-black uppercase text-chess-muted tracking-widest">Hasil Pencarian</h3>
                    <button onClick={() => setSearchResults([])} className="text-[10px] font-bold text-chess-green hover:underline">TUTUP HASIL</button>
                  </div>
                  {searchResults.map((player) => (
                    <div key={player.id} className="flex items-center justify-between p-4 bg-chess-green/5 border border-chess-green/20 rounded-2xl">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-full bg-chess-bg border border-chess-green flex items-center justify-center font-bold text-chess-green">
                          {player.username.charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <p className="font-bold">{player.username}</p>
                          <p className="text-xs text-chess-muted">{player.points} pts</p>
                        </div>
                      </div>
                      <button 
                        onClick={() => sendFriendRequest(player.username)}
                        className="p-2 bg-chess-green hover:brightness-110 rounded-lg text-white transition-all shadow-lg shadow-chess-green/20"
                      >
                        <UserPlus className="w-5 h-5" />
                      </button>
                    </div>
                  ))}
                </motion.div>
              )}
            </AnimatePresence>

            {/* List Components */}
            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
              {activeTab === 'friends' ? (
                <>
                  {friends.length > 0 ? friends.map((friend) => (
                    <div key={friend.id} className="flex items-center justify-between p-5 bg-chess-card border border-chess-border rounded-2xl hover:border-chess-green/30 transition-all group">
                      <div className="flex items-center gap-4">
                        <div className="relative">
                          <div className="w-14 h-14 rounded-full bg-chess-bg border border-chess-border flex items-center justify-center font-black text-2xl group-hover:scale-105 transition-transform">
                            {friend.username.charAt(0).toUpperCase()}
                          </div>
                          <div className={`absolute bottom-0 right-0 w-4 h-4 rounded-full border-2 border-chess-card ${friend.online ? 'bg-chess-green' : 'bg-chess-muted'}`} />
                        </div>
                        <div>
                          <p className="font-bold text-lg">{friend.username}</p>
                          <p className="text-xs text-chess-muted">{friend.points} pts • {friend.online ? 'Online' : 'Offline'}</p>
                        </div>
                      </div>
                      <div className="flex gap-2">
                        <button 
                          onClick={() => createChallenge(friend.id)}
                          disabled={!friend.online}
                          className="flex items-center gap-2 px-4 py-2 bg-chess-green hover:brightness-110 disabled:grayscale disabled:opacity-30 rounded-xl text-xs font-black transition-all"
                        >
                          <Play className="w-4 h-4 fill-current" />
                          TANTANG
                        </button>
                        <button className="p-2 bg-white/5 hover:bg-white/10 text-chess-muted hover:text-red-400 rounded-xl transition-all">
                          <UserMinus className="w-5 h-5" />
                        </button>
                      </div>
                    </div>
                  )) : (
                    <div className="p-20 text-center text-chess-muted border border-dashed border-chess-border rounded-3xl">
                      <Mail className="w-12 h-12 mx-auto mb-4 opacity-20" />
                      <p className="font-bold text-lg">Belum ada teman</p>
                      <p className="text-sm">Gunakan kolom pencarian di atas untuk menambahkan teman baru.</p>
                    </div>
                  )}
                </>
              ) : (
                <div className="grid gap-8">
                  {/* Incoming */}
                  <div className="space-y-3">
                    <h3 className="text-xs font-black uppercase text-chess-muted tracking-widest pl-2">Permintaan Masuk</h3>
                    {requests.incoming.length > 0 ? requests.incoming.map((req) => (
                      <div key={req.id} className="flex items-center justify-between p-4 bg-chess-card border border-chess-border rounded-2xl">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-full bg-chess-bg border border-chess-border flex items-center justify-center font-bold">
                            {req.username.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <p className="font-bold">{req.username}</p>
                            <p className="text-xs text-chess-muted">{req.points} pts</p>
                          </div>
                        </div>
                        <div className="flex gap-2">
                          <button onClick={() => acceptRequest(req.id)} className="p-2 bg-chess-green rounded-lg text-white hover:brightness-110 transition-all">
                            <Check className="w-5 h-5" />
                          </button>
                          <button className="p-2 bg-red-500/20 text-red-500 rounded-lg hover:bg-red-500/30 transition-all">
                            <X className="w-5 h-5" />
                          </button>
                        </div>
                      </div>
                    )) : (
                      <div className="p-8 text-center text-xs text-chess-muted bg-white/5 rounded-2xl border border-dashed border-chess-border/50">
                        Tidak ada permintaan masuk
                      </div>
                    )}
                  </div>

                  {/* Outgoing */}
                  <div className="space-y-3">
                    <h3 className="text-xs font-black uppercase text-chess-muted tracking-widest pl-2">Permintaan Terkirim</h3>
                    {requests.outgoing.length > 0 ? requests.outgoing.map((req) => (
                      <div key={req.id} className="flex items-center justify-between p-4 bg-chess-card/50 border border-chess-border rounded-2xl italic opacity-70">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-full bg-chess-bg border border-chess-border flex items-center justify-center font-bold">
                            {req.username.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <p className="font-bold">{req.username}</p>
                            <p className="text-xs text-chess-muted">Menunggu konfirmasi...</p>
                          </div>
                        </div>
                        <button className="text-xs text-red-400 hover:underline">Batalkan</button>
                      </div>
                    )) : (
                      <div className="p-8 text-center text-xs text-chess-muted bg-white/5 rounded-2xl border border-dashed border-chess-border/50">
                        Tidak ada permintaan terkirim
                      </div>
                    )}
                  </div>
                </div>
              )}
            </motion.div>
          </div>
        </main>
      </div>
    </div>
  );
};

export default Friends;
