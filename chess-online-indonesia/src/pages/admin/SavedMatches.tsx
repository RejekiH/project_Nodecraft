import React, { useState, useEffect } from 'react';
import { api } from '../../services/api';
import { Save, Search, Download, Calendar, ArrowRight, Eye } from 'lucide-react';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import Modal from '../../components/ui/Modal';
import ChessBoard from '../../components/chess/ChessBoard';
import { parseFen, INITIAL_FEN } from '../../utils/boardUtils';

const SavedMatches: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [matches, setMatches] = useState<any[]>([]);
  const [search, setSearch] = useState('');
  const [replayMatch, setReplayMatch] = useState<any>(null);

  const fetchData = async () => {
    try {
      const data = await api.get<{ matches: any[] }>('/admin/matches/saved');
      setMatches(data.matches);
    } catch (err) {
      console.error('Failed to fetch saved matches', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
     fetchData();
  }, []);

  const filteredMatches = matches.filter(m => 
    m.white.toLowerCase().includes(search.toLowerCase()) || 
    m.black.toLowerCase().includes(search.toLowerCase())
  );

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-8 animate-fade-in">
       <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold italic tracking-tighter uppercase mb-1">Arsip Pertandingan</h1>
          <p className="text-slate-500 text-sm font-bold uppercase tracking-widest">Database Historis Semua Sesi Permainan</p>
        </div>
        <button className="flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-lg shadow-indigo-600/20">
            <Download className="w-5 h-5" /> EKSPOR DATABASE (SQL/CSV)
        </button>
      </div>

       {/* Filters */}
       <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1 relative group">
                <Search className="absolute left-5 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 group-focus-within:text-chess-indigo transition-colors" />
                <input 
                    type="text" 
                    placeholder="Cari berdasarkan username pemain..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="w-full bg-slate-100 border-none rounded-full py-4 pl-14 pr-6 outline-none focus:ring-2 focus:ring-chess-indigo/20 transition-all text-slate-900"
                />
            </div>
            <div className="flex gap-2">
                <button className="px-6 py-4 bg-white border border-slate-200 rounded-2xl flex items-center gap-3 hover:bg-slate-50 transition-all text-slate-500 font-bold shadow-sm">
                    <Calendar className="w-5 h-5" />
                    <span className="text-xs uppercase tracking-widest">Rentang Tanggal</span>
                </button>
            </div>
        </div>

        <div className="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full text-left">
                    <thead>
                        <tr className="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50 bg-slate-50/50">
                            <th className="px-8 py-5">Match ID</th>
                            <th className="px-8 py-5">Pemain (P vs H)</th>
                            <th className="px-8 py-5 text-center">Pemenang</th>
                            <th className="px-8 py-5 text-center">Detail</th>
                            <th className="px-8 py-5 text-center">Tanggal</th>
                            <th className="px-8 py-5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50">
                        {filteredMatches.map((match) => (
                            <tr 
                                key={match.id} 
                                className="hover:bg-slate-50/50 transition-colors group cursor-pointer"
                                onClick={() => setReplayMatch(match)}
                            >
                                <td className="px-8 py-5 font-bold text-chess-indigo">#{match.id}</td>
                                <td className="px-8 py-5">
                                    <div className="flex items-center gap-3">
                                        <div className="flex items-center gap-2">
                                            <span className="font-bold text-sm text-slate-800">{match.white}</span>
                                            <ArrowRight className="w-3 h-3 text-slate-300" />
                                            <span className="font-bold text-sm text-slate-800">{match.black}</span>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-center">
                                    <span className={`px-3 py-1 rounded-lg text-[10px] font-bold uppercase ${match.winner === 'Draw' ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-600'}`}>
                                        {match.winner}
                                    </span>
                                </td>
                                <td className="px-8 py-5 text-center text-slate-600">
                                    <div className="flex flex-col items-center">
                                        <p className="text-xs font-bold">{match.total_moves} Moves</p>
                                        <p className="text-[10px] font-bold text-slate-400 uppercase tracking-tight">{match.duration}</p>
                                    </div>
                                </td>
                                <td className="px-8 py-5 text-center text-xs text-slate-400 italic font-medium">
                                    {match.date}
                                </td>
                                <td className="px-8 py-5 text-right">
                                    <button 
                                        onClick={(e) => { e.stopPropagation(); setReplayMatch(match); }}
                                        className="p-2.5 bg-slate-50 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-chess-indigo transition-all border border-slate-100"
                                    >
                                        <Eye className="w-5 h-5" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {filteredMatches.length === 0 && (
                <div className="p-20 text-center text-chess-muted">
                    Database arsip kosong atau tidak ditemukan.
                </div>
            )}
        </div>

        {/* Quick Replay Modal */}
        <Modal isOpen={replayMatch !== null} onClose={() => setReplayMatch(null)} title={`Tinjauan Match #${replayMatch?.id}`}>
            {replayMatch && (
                <div className="space-y-6">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="p-4 bg-white/5 rounded-2xl border border-chess-border text-center">
                            <p className="text-[10px] font-black text-chess-muted uppercase mb-1 tracking-widest">White</p>
                            <p className="font-bold">{replayMatch.white}</p>
                        </div>
                        <div className="p-4 bg-white/5 rounded-2xl border border-chess-border text-center">
                            <p className="text-[10px] font-black text-chess-muted uppercase mb-1 tracking-widest">Black</p>
                            <p className="font-bold">{replayMatch.black}</p>
                        </div>
                    </div>
                    
                    <div className="aspect-square bg-chess-bg border border-chess-border rounded-xl p-2 sm:p-4 mx-auto max-w-[400px]">
                        <ChessBoard 
                            boardState={parseFen(INITIAL_FEN)} // Admin replay logic could follow moves
                            onSquareClick={() => {}}
                            selectedSquare={null}
                            legalMoves={[]}
                            lastMove={null}
                            checkSquare={null}
                            orientation="white"
                        />
                    </div>

                    <div className="flex items-center justify-between text-xs font-black uppercase text-chess-muted">
                        <span>Pemenang: <span className="text-chess-green">{replayMatch.winner}</span></span>
                        <span>Total: {replayMatch.total_moves} Langkah</span>
                    </div>

                    <button 
                        onClick={() => setReplayMatch(null)}
                        className="w-full py-4 bg-red-600 rounded-xl font-black text-xl hover:brightness-110 transition-all shadow-xl shadow-red-600/20"
                    >
                        TUTUP TINJAUAN
                    </button>
                </div>
            )}
        </Modal>
    </div>
  );
};

export default SavedMatches;
