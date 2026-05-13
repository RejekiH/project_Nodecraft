import React, { useState, useEffect } from 'react';
import { api } from '../../services/api';
import { Mail, Search, ShieldCheck, ShieldAlert, MoreVertical, Filter, Download } from 'lucide-react';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import { useNotification } from '../../contexts/NotificationContext';
import ConfirmDialog from '../../components/ui/ConfirmDialog';

const Players: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [players, setPlayers] = useState<any[]>([]);
  const [search, setSearch] = useState('');
  const [suspendModal, setSuspendModal] = useState<{ open: boolean, id: number | null, username: string }>({ open: false, id: null, username: '' });
  
  const { showToast } = useNotification();

  const fetchData = async () => {
    try {
      const data = await api.get<{ players: any[] }>('/admin/players');
      setPlayers(data.players);
    } catch (err) {
      console.error('Failed to fetch players data', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleToggleStatus = async (id: number, currentStatus: string) => {
    const newStatus = currentStatus === 'Aktif' ? 'Suspended' : 'Aktif';
    try {
      await api.put(`/admin/players/${id}/status`, { status: newStatus });
      showToast(`Status ${players.find(p => p.id === id)?.username} diperbarui menjadi ${newStatus}`, 'success');
      fetchData();
    } catch (err) {
      showToast('Gagal mengubah status pemain', 'error');
    }
  };

  const filteredPlayers = players.filter(p => 
    p.username.toLowerCase().includes(search.toLowerCase()) || 
    p.name.toLowerCase().includes(search.toLowerCase())
  );

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-8 animate-fade-in">
       <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-black italic tracking-tighter uppercase mb-1">Manajemen Pemain</h1>
          <p className="text-chess-muted text-sm font-bold uppercase tracking-widest">Kendalikan Akun dan Aktivitas Komunitas</p>
        </div>
        <div className="flex gap-2">
            <button className="flex items-center gap-2 px-4 py-2 bg-white/5 hover:bg-white/10 rounded-lg text-xs font-bold transition-all border border-chess-border">
                <Download className="w-4 h-4" /> EKSPOR CSV
            </button>
        </div>
      </div>

       {/* Filters */}
       <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1 relative group">
                <Search className="absolute left-5 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within:text-chess-indigo transition-colors" />
                <input 
                    type="text" 
                    placeholder="Cari pemain berdasarkan username, nama, atau ID..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-full bg-slate-100 border-none rounded-full py-4 pl-14 pr-6 outline-none focus:ring-2 focus:ring-chess-indigo/20 transition-all text-slate-900"
                />
            </div>
            <button className="px-6 py-4 bg-white border border-slate-200 rounded-2xl flex items-center gap-3 hover:bg-slate-50 transition-all text-slate-600 shadow-sm font-bold">
                <Filter className="w-5 h-5" />
                <span className="text-xs uppercase tracking-widest">Filter Lanjutan</span>
            </button>
        </div>

        <div className="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full text-left">
                    <thead>
                        <tr className="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50 bg-slate-50/50">
                            <th className="px-8 py-5">Identitas Pemain</th>
                            <th className="px-8 py-5 text-center">Poin</th>
                            <th className="px-8 py-5 text-center">Total Match</th>
                            <th className="px-8 py-5 text-center">Status Akun</th>
                            <th className="px-8 py-5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                        {filteredPlayers.map((player) => (
                            <tr key={player.id} className="hover:bg-slate-50/50 transition-colors group">
                                <td className="px-8 py-5">
                                    <div className="flex items-center gap-4">
                                        <div className="w-12 h-12 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center font-bold text-chess-indigo text-xl">
                                            {player.username.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="font-bold text-slate-800 flex items-center gap-2">
                                                {player.username}
                                                <span className="text-[10px] bg-slate-100 px-2 py-0.5 rounded-lg text-slate-400 font-bold tracking-tight">ID: {player.id}</span>
                                            </p>
                                            <p className="text-xs text-slate-500 font-medium">{player.name}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-center">
                                    <span className="font-bold text-emerald-600 text-lg">{player.points}</span>
                                </td>
                                <td className="px-8 py-5 text-center font-bold text-slate-400">
                                    {player.total_match}
                                </td>
                                <td className="px-8 py-5">
                                    <div className="flex flex-col items-center">
                                        <span className={`px-3 py-1 rounded-lg text-[10px] font-bold uppercase ${player.status === 'Aktif' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}`}>
                                            {player.status}
                                        </span>
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-right">
                                    <div className="flex items-center justify-end gap-2">
                                        <button 
                                            title="Kirim Pesan"
                                            className="p-2.5 hover:bg-slate-50 rounded-xl text-slate-400 hover:text-chess-indigo transition-all"
                                        >
                                            <Mail className="w-4.5 h-4.5" />
                                        </button>
                                        <button 
                                            onClick={() => {
                                                if (player.status === 'Aktif') {
                                                    setSuspendModal({ open: true, id: player.id, username: player.username });
                                                } else {
                                                    handleToggleStatus(player.id, player.status);
                                                }
                                            }}
                                            className={`p-2.5 rounded-xl transition-all ${player.status === 'Aktif' ? 'text-rose-400 hover:bg-rose-50' : 'text-emerald-400 hover:bg-emerald-50'}`}
                                            title={player.status === 'Aktif' ? 'Suspend Akun' : 'Aktifkan Akun'}
                                        >
                                            {player.status === 'Aktif' ? <ShieldAlert className="w-4.5 h-4.5" /> : <ShieldCheck className="w-4.5 h-4.5" />}
                                        </button>
                                        <button className="p-2.5 hover:bg-slate-50 rounded-xl text-slate-400 transition-all">
                                            <MoreVertical className="w-4.5 h-4.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {filteredPlayers.length === 0 && (
                <div className="p-20 text-center text-chess-muted">
                    Tidak ada pemain yang cocok dengan pencarian.
                </div>
            )}
        </div>

        <ConfirmDialog 
            isOpen={suspendModal.open}
            onClose={() => setSuspendModal({ ...suspendModal, open: false })}
            onConfirm={() => suspendModal.id && handleToggleStatus(suspendModal.id, 'Aktif')}
            title="Konfirmasi Suspend"
            message={`Apakah Anda yakin ingin menangguhkan akun @${suspendModal.username}? Pemain ini tidak akan bisa masuk ke sistem sampai statusnya dikembalikan.`}
            confirmLabel="Ya, Suspend Akun"
            isDestructive
        />
    </div>
  );
};

export default Players;
