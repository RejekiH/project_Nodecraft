import React, { useState, useEffect } from 'react';
import Navbar from '../components/layout/Navbar';
import Sidebar from '../components/layout/Sidebar';
import { Play, PlusCircle, LogIn, Copy, Check, Users, Shield, Clock } from 'lucide-react';
import { api } from '../services/api';
import { useNotification } from '../contexts/NotificationContext';
import { useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'motion/react';
import LoadingSpinner from '../components/ui/LoadingSpinner';

const Room: React.FC = () => {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [rooms, setRooms] = useState<any[]>([]);
  const [roomCode, setRoomCode] = useState('');
  const [creating, setCreating] = useState(false);
  const [createdRoom, setCreatedRoom] = useState<any>(null);
  const [copied, setCopied] = useState(false);

  const navigate = useNavigate();
  const { showToast } = useNotification();

  const fetchRooms = async () => {
    try {
      const data = await api.get<{ rooms: any[] }>('/room/list');
      setRooms(data.rooms);
    } catch (err) {
      console.error('Failed to fetch rooms', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRooms();
    const interval = setInterval(fetchRooms, 10000);
    return () => clearInterval(interval);
  }, []);

  const handleCreateRoom = async () => {
    setCreating(true);
    try {
      const data = await api.post<{ room: any }>('/room/create');
      setCreatedRoom(data.room);
      showToast('Room berhasil dibuat!', 'success');
      // After room is created, they can wait here or redirect if backend auto-joins them
    } catch (err: any) {
      showToast(err.message || 'Gagal membuat room', 'error');
    } finally {
      setCreating(false);
    }
  };

  const handleJoinRoom = async (code?: string) => {
    const finalCode = code || roomCode;
    if (!finalCode) {
      showToast('Masukkan kode room dulu', 'warning');
      return;
    }

    try {
      const data = await api.post<{ room: any }>('/room/join', { room_code: finalCode.toUpperCase() });
      showToast('Berhasil bergabung!', 'success');
      navigate(`/game/${data.room.id}`);
    } catch (err: any) {
      showToast(err.message || 'Gagal bergabung ke room', 'error');
    }
  };

  const copyCode = () => {
    if (!createdRoom) return;
    navigator.clipboard.writeText(createdRoom.room_code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className="flex flex-col min-h-screen bg-chess-bg text-chess-text">
      <Navbar sidebarOpen={sidebarOpen} setSidebarOpen={setSidebarOpen} />
      <div className="flex flex-1">
        <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        
        <main className="flex-1 p-4 sm:p-8 overflow-y-auto max-w-7xl mx-auto w-full">
          <div className="mb-12">
            <h1 className="text-4xl font-black italic tracking-tighter mb-2 underline decoration-chess-green decoration-4 underline-offset-8 uppercase">Ruang Bermain</h1>
            <p className="text-chess-muted">Buat room baru untuk bermain dengan teman atau gabung ke room yang ada.</p>
          </div>

          <div className="grid md:grid-cols-2 gap-8 mb-12">
            {/* Create Room */}
            <div className={`bg-chess-card border-2 rounded-3xl p-8 flex flex-col items-center text-center transition-all ${createdRoom ? 'border-chess-green' : 'border-chess-border'}`}>
              {!createdRoom ? (
                <>
                  <div className="w-20 h-20 bg-chess-green/10 rounded-full flex items-center justify-center mb-6">
                    <PlusCircle className="w-10 h-10 text-chess-green" />
                  </div>
                  <h3 className="text-2xl font-bold mb-4">Buat Room Baru</h3>
                  <p className="text-chess-muted mb-8">Dapatkan kode unik untuk dibagikan ke temanmu agar mereka bisa bergabung.</p>
                  <button 
                    onClick={handleCreateRoom}
                    disabled={creating}
                    className="w-full bg-chess-green hover:brightness-110 disabled:opacity-50 py-4 rounded-xl font-bold flex items-center justify-center gap-3 transition-all active:scale-95"
                  >
                    {creating ? <div className="w-6 h-6 border-4 border-white/20 border-t-white rounded-full animate-spin" /> : 'BUAT SEKARANG'}
                  </button>
                </>
              ) : (
                <motion.div initial={{ scale: 0.9, opacity: 0 }} animate={{ scale: 1, opacity: 1 }} className="w-full">
                  <h3 className="text-2xl font-bold mb-2">Room Berhasil Dibuat</h3>
                  <p className="text-chess-muted mb-6">Kirimkan kode ini ke temanmu:</p>
                  <div className="bg-chess-bg border border-chess-green/30 rounded-2xl p-6 mb-6 relative group">
                    <span className="text-5xl font-black tracking-[0.2em] text-chess-green">{createdRoom.room_code}</span>
                    <button 
                      onClick={copyCode}
                      className="absolute right-4 top-1/2 -translate-y-1/2 p-2 hover:bg-white/5 rounded-lg text-chess-muted hover:text-white transition-all"
                    >
                      {copied ? <Check className="w-6 h-6 text-chess-green" /> : <Copy className="w-6 h-6" />}
                    </button>
                  </div>
                  <div className="flex items-center justify-center gap-3 text-chess-muted">
                    <div className="w-2 h-2 bg-chess-green rounded-full animate-pulse" />
                    <span>Menunggu lawan bergabung...</span>
                  </div>
                  <button 
                    onClick={() => navigate(`/game/${createdRoom.id}`)}
                    className="w-full bg-white/5 border border-chess-border hover:bg-white/10 mt-8 py-3 rounded-xl font-bold transition-all"
                  >
                    MASUK KE ROOM
                  </button>
                </motion.div>
              )}
            </div>

            {/* Join Room */}
            <div className="bg-chess-card border-2 border-chess-border rounded-3xl p-8 flex flex-col items-center text-center">
              <div className="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-6">
                <LogIn className="w-10 h-10 text-chess-muted" />
              </div>
              <h3 className="text-2xl font-bold mb-4">Gabung Room</h3>
              <p className="text-chess-muted mb-8">Punya kode room dari teman? Masukkan kodenya di bawah ini.</p>
              <div className="w-full space-y-4">
                <input 
                  type="text"
                  placeholder="KODE ROOM (6 DIGIT)"
                  maxLength={6}
                  value={roomCode}
                  onChange={(e) => setRoomCode(e.target.value.toUpperCase())}
                  className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-4 text-center text-2xl font-black tracking-widest outline-none focus:border-chess-green transition-all"
                />
                <button 
                  onClick={() => handleJoinRoom()}
                  className="w-full bg-chess-bg border-2 border-chess-green text-chess-green hover:bg-chess-green hover:text-white py-4 rounded-xl font-bold flex items-center justify-center gap-3 transition-all active:scale-95"
                >
                  GABUNG ROOM
                </button>
              </div>
            </div>
          </div>

          {/* Available Rooms */}
          <div className="mt-12 bg-chess-card border border-chess-border rounded-3xl overflow-hidden">
            <div className="p-6 border-b border-chess-border flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Users className="w-5 h-5 text-chess-green" />
                <h3 className="text-xl font-bold">Room Tersedia</h3>
              </div>
              <span className="text-xs text-chess-muted font-bold uppercase tracking-widest">Auto-refresh setiap 10 detik</span>
            </div>
            
            {loading ? (
              <div className="p-12"><LoadingSpinner /></div>
            ) : rooms.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="text-left text-chess-muted text-xs font-black uppercase tracking-wider border-b border-chess-border">
                      <th className="px-6 py-4">Kode Room</th>
                      <th className="px-6 py-4">Host</th>
                      <th className="px-6 py-4 text-center">Status</th>
                      <th className="px-6 py-4">Dibuat Pada</th>
                      <th className="px-6 py-4"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-chess-border">
                    {rooms.map((room) => (
                      <tr key={room.id} className="hover:bg-white/5 transition-colors group">
                        <td className="px-6 py-4 font-black text-chess-green tracking-widest">{room.room_code}</td>
                        <td className="px-6 py-4 font-bold">{room.host?.username || 'Unknown'}</td>
                        <td className="px-6 py-4">
                          <span className={`mx-auto block w-fit px-3 py-1 rounded-full text-[10px] font-black uppercase ${room.status === 'waiting' ? 'bg-chess-green/10 text-chess-green' : 'bg-red-500/10 text-red-400'}`}>
                            {room.status}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-sm text-chess-muted">
                           {new Date(room.created_at).toLocaleTimeString()}
                        </td>
                        <td className="px-6 py-4 text-right">
                          <button 
                            onClick={() => handleJoinRoom(room.room_code)}
                            disabled={room.status !== 'waiting'}
                            className="bg-chess-green hover:brightness-110 disabled:grayscale disabled:opacity-50 px-6 py-2 rounded-lg font-bold text-sm transition-all"
                          >
                            GABUNG
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="p-16 text-center text-chess-muted flex flex-col items-center gap-4">
                <Shield className="w-12 h-12 opacity-20" />
                <p>Belum ada room yang tersedia saat ini.</p>
              </div>
            )}
          </div>
        </main>
      </div>
    </div>
  );
};

export default Room;
