import React, { useState, useEffect } from 'react';
import { api } from '../../services/api';
import { Gamepad2, ShieldClose, RefreshCcw, Wifi, Clock, Sword } from 'lucide-react';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import { useNotification } from '../../contexts/NotificationContext';
import ConfirmDialog from '../../components/ui/ConfirmDialog';

const ActiveMatches: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [matches, setMatches] = useState<any[]>([]);
  const [forceEndData, setForceEndData] = useState<{ id: number | null, roomId: string | null }>({ id: null, roomId: null });
  
  const { showToast } = useNotification();

  const fetchData = async () => {
    try {
      const data = await api.get<{ matches: any[] }>('/admin/matches/active');
      setMatches(data.matches);
    } catch (err) {
      console.error('Failed to fetch active matches', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, 10000);
    return () => clearInterval(interval);
  }, []);

  const handleForceEnd = async () => {
    if (!forceEndData.id) return;
    try {
      await api.post(`/admin/matches/${forceEndData.id}/force-end`);
      showToast(`Pertandingan ${forceEndData.roomId} berhasil dihentikan paksa`, 'success');
      fetchData();
    } catch (err) {
      showToast('Gagal menghentikan pertandingan', 'error');
    }
  };

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-8 animate-fade-in">
       <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-black italic tracking-tighter uppercase mb-1">Match Live</h1>
          <p className="text-chess-muted text-sm font-bold uppercase tracking-widest">Pantau Pertandingan yang Sedang Berjalan</p>
        </div>
        <div className="flex items-center gap-4">
            <div className="flex items-center gap-2 text-xs font-black uppercase text-chess-muted">
                <div className="w-2 h-2 bg-red-600 rounded-full animate-pulse" />
                <span>{matches.length} AKTIF</span>
            </div>
            <button 
              onClick={() => { setLoading(true); fetchData(); }}
              className="p-2 hover:bg-white/5 rounded-lg border border-chess-border"
            >
              <RefreshCcw className="w-5 h-5 text-chess-muted" />
            </button>
        </div>
      </div>

       <div className="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full text-left">
                    <thead>
                        <tr className="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50 bg-slate-50/50">
                            <th className="px-8 py-5">Room Details</th>
                            <th className="px-8 py-5 text-center">Pemain Putih</th>
                            <th className="px-8 py-5 text-center">Pemain Hitam</th>
                            <th className="px-8 py-5 text-center">Kondisi</th>
                            <th className="px-8 py-5 text-center">Server</th>
                            <th className="px-8 py-5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                        {matches.map((match) => (
                            <tr key={match.id} className="hover:bg-slate-50/50 transition-colors group">
                                <td className="px-8 py-5">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                                            <Gamepad2 className="w-5 h-5 text-chess-indigo" />
                                        </div>
                                        <div>
                                            <p className="font-bold text-chess-indigo tracking-widest text-sm">{match.room_id}</p>
                                            <div className="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase">
                                                <div className="flex items-center gap-1"><Clock className="w-3 h-3" /> {match.duration}</div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-center">
                                    <div className="inline-flex flex-col items-center">
                                        <div className="w-9 h-9 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs mb-1 text-slate-600">
                                            {match.white.charAt(0).toUpperCase()}
                                        </div>
                                        <span className="text-xs font-bold text-slate-700">{match.white}</span>
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-center">
                                    <div className="inline-flex flex-col items-center">
                                        <div className="w-9 h-9 rounded-full bg-slate-900 border border-slate-700 shadow-lg flex items-center justify-center font-bold text-xs mb-1 text-white">
                                            {match.black.charAt(0).toUpperCase()}
                                        </div>
                                        <span className="text-xs font-bold text-slate-700">{match.black}</span>
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-center">
                                    <div className="inline-flex flex-col items-center">
                                        <div className="flex items-center gap-1.5 text-[10px] font-bold text-emerald-600 mb-1.5 tracking-wider uppercase">
                                            <Sword className="w-3 h-3" /> 
                                            MOVE {match.move_number}
                                        </div>
                                        <div className="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div className="h-full bg-emerald-500 transition-all duration-500" style={{ width: '40%' }} />
                                        </div>
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-center">
                                    <div className="inline-flex items-center gap-2 text-[10px] font-bold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-lg">
                                        <Wifi className="w-3 h-3" /> {match.server}
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-right">
                                    <button 
                                        onClick={() => setForceEndData({ id: match.id, roomId: match.room_id })}
                                        className="inline-flex items-center gap-2 px-4 py-2 bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white rounded-xl text-xs font-bold transition-all shadow-sm border border-rose-100"
                                    >
                                        <ShieldClose className="w-4 h-4" /> FORCE END
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {matches.length === 0 && (
                <div className="p-20 text-center text-chess-muted flex flex-col items-center gap-4">
                    <Gamepad2 className="w-12 h-12 opacity-10" />
                    <p className="text-sm font-bold uppercase tracking-widest leading-tight">Tidak ada pertandingan aktif saat ini.</p>
                </div>
            )}
        </div>

        <ConfirmDialog 
            isOpen={forceEndData.id !== null}
            onClose={() => setForceEndData({ id: null, roomId: null })}
            onConfirm={handleForceEnd}
            title="Hentikan Paksa Pertandingan?"
            message={`Tindakan ini akan menghentikan pertandingan ${forceEndData.roomId} secara paksa. Data akan disimpan sebagai match yang dibatalkan.`}
            confirmLabel="Ya, Berhenti Paksa"
            isDestructive
        />
    </div>
  );
};

export default ActiveMatches;
