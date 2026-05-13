import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import Navbar from '../components/layout/Navbar';
import Sidebar from '../components/layout/Sidebar';
import { Trophy, History, Play, SkipBack, SkipForward, FastForward, Rewind, CornerUpLeft, Award, Clock, Sword } from 'lucide-react';
import { api } from '../services/api';
import ChessBoard from '../components/chess/ChessBoard';
import { parseFen, INITIAL_FEN } from '../utils/boardUtils';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { motion } from 'motion/react';

const GameResult: React.FC = () => {
  const { matchId } = useParams();
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [match, setMatch] = useState<any>(null);
  
  // Replay State
  const [moveIndex, setMoveIndex] = useState(-1);
  const [currentFen, setCurrentFen] = useState(INITIAL_FEN);
  const [isPlaying, setIsPlaying] = useState(false);

  useEffect(() => {
    const fetchMatch = async () => {
      try {
        const data = await api.get<{ match: any }>(`/backup/match/${matchId}`);
        setMatch(data.match);
      } catch (err) {
        console.error('Failed to fetch match result', err);
      } finally {
        setLoading(false);
      }
    };
    fetchMatch();
  }, [matchId]);

  // Replay logic
  useEffect(() => {
    if (!match || !match.moves) return;
    if (moveIndex === -1) {
      setCurrentFen(INITIAL_FEN);
    } else {
      // In a real app, match.moves would contain FENs or we use chess.js to replay
      // For this demo, we'll assume moves are objects with board_state
      const move = match.moves[moveIndex];
      if (move?.board_state) setCurrentFen(move.board_state);
    }
  }, [moveIndex, match]);

  useEffect(() => {
    let interval: any = null;
    if (isPlaying && match && moveIndex < match.moves.length - 1) {
      interval = setInterval(() => {
        setMoveIndex(prev => prev + 1);
      }, 1000);
    } else {
      setIsPlaying(false);
      clearInterval(interval);
    }
    return () => clearInterval(interval);
  }, [isPlaying, moveIndex, match]);

  if (loading) return <LoadingSpinner />;

  const isWin = match?.result === 'win';
  const isLoss = match?.result === 'loss';

  return (
    <div className="flex flex-col min-h-screen bg-chess-bg text-chess-text">
      <Navbar sidebarOpen={sidebarOpen} setSidebarOpen={setSidebarOpen} />
      <div className="flex flex-1">
        <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
        
        <main className="flex-1 p-4 sm:p-8 overflow-y-auto max-w-6xl mx-auto w-full">
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="space-y-8">
            
            {/* Header Result */}
            <div className={`relative p-8 rounded-3xl border-4 overflow-hidden text-center ${isWin ? 'bg-chess-green/10 border-chess-green shadow-[0_0_30px_#81B64C22]' : isLoss ? 'bg-red-500/10 border-red-500 shadow-[0_0_30px_#ef444422]' : 'bg-white/5 border-chess-border'}`}>
              <div className="relative z-10">
                <h1 className="text-5xl sm:text-7xl font-black italic mb-2 tracking-tighter uppercase">
                  {isWin ? 'MENANG!' : isLoss ? 'KALAH' : 'REMIS'}
                </h1>
                <p className="text-xl font-bold opacity-70 mb-8 italic uppercase tracking-[0.3em]">
                  {match?.white_player?.username} vs {match?.black_player?.username}
                </p>
                
                <div className="flex items-center justify-center gap-8 mb-4">
                  <div className="text-center">
                    <p className="text-[10px] font-black uppercase text-chess-muted tracking-widest mb-1">Durasi</p>
                    <div className="flex items-center gap-2 font-bold bg-white/5 px-4 py-2 rounded-xl border border-chess-border/50">
                      <Clock className="w-4 h-4 text-chess-green" />
                      {match?.duration}
                    </div>
                  </div>
                  <div className="text-center">
                    <p className="text-[10px] font-black uppercase text-chess-muted tracking-widest mb-1">Total Langkah</p>
                    <div className="flex items-center gap-2 font-bold bg-white/5 px-4 py-2 rounded-xl border border-chess-border/50">
                      <Sword className="w-4 h-4 text-chess-green" />
                      {match?.moves?.length || 0}
                    </div>
                  </div>
                  <div className="text-center">
                    <p className="text-[10px] font-black uppercase text-chess-muted tracking-widest mb-1">Perubahan Poin</p>
                    <div className={`flex items-center gap-2 font-black text-2xl ${isWin ? 'text-chess-green' : isLoss ? 'text-red-400' : ''}`}>
                      <Award className="w-5 h-5" />
                      {isWin ? '+15' : isLoss ? '-5' : '0'}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Replay Section */}
            <div className="grid lg:grid-cols-2 gap-8 items-start">
              <div className="space-y-4">
                 <h3 className="text-xl font-bold flex items-center gap-2">
                   <History className="w-5 h-5 text-chess-green" />
                   Replay Pertandingan
                 </h3>
                 <div className="aspect-square bg-chess-card border border-chess-border rounded-xl p-2 sm:p-4">
                    <ChessBoard 
                      boardState={parseFen(currentFen)}
                      onSquareClick={() => {}}
                      selectedSquare={null}
                      legalMoves={[]}
                      lastMove={match?.moves[moveIndex] ? { from: match.moves[moveIndex].from, to: match.moves[moveIndex].to } : null}
                      checkSquare={null}
                      orientation="white"
                    />
                 </div>
                 
                 {/* Replay Controls */}
                 <div className="flex items-center justify-center gap-2 bg-chess-card border border-chess-border p-4 rounded-2xl">
                    <button onClick={() => setMoveIndex(-1)} className="p-3 hover:bg-white/5 rounded-xl transition-all"><SkipBack /></button>
                    <button onClick={() => setMoveIndex(prev => Math.max(-1, prev - 1))} className="p-3 hover:bg-white/5 rounded-xl transition-all"><Rewind /></button>
                    <button 
                      onClick={() => setIsPlaying(!isPlaying)}
                      className="w-14 h-14 bg-chess-green hover:brightness-110 rounded-full flex items-center justify-center text-white shadow-lg shadow-chess-green/20 transition-all active:scale-95"
                    >
                      {isPlaying ? <span className="text-2xl">II</span> : <Play className="fill-current" />}
                    </button>
                    <button onClick={() => setMoveIndex(prev => Math.min(match.moves.length - 1, prev + 1))} className="p-3 hover:bg-white/5 rounded-xl transition-all"><FastForward /></button>
                    <button onClick={() => setMoveIndex(match.moves.length - 1)} className="p-3 hover:bg-white/5 rounded-xl transition-all"><SkipForward /></button>
                 </div>
              </div>

              {/* Match Details & Actions */}
              <div className="space-y-8">
                <div className="bg-chess-card border border-chess-border rounded-3xl p-8">
                  <h3 className="text-xl font-bold mb-6">Analisis Pertandingan</h3>
                  <div className="space-y-6">
                    <div className="flex items-center justify-between p-4 bg-white/5 border border-chess-border rounded-2xl">
                      <div className="flex items-center gap-4">
                        <div className="w-12 h-12 rounded-full bg-chess-green/10 flex items-center justify-center font-bold text-chess-green text-xl">W</div>
                        <div>
                          <p className="font-bold">{match?.white_player?.username}</p>
                          <p className="text-[10px] uppercase font-black text-chess-muted">Pemain Putih</p>
                        </div>
                      </div>
                      <span className={`font-black uppercase tracking-widest ${match.winner === 'white' ? 'text-chess-green' : 'text-chess-muted'}`}>{match.winner === 'white' ? 'WINNER' : ''}</span>
                    </div>

                    <div className="flex items-center justify-between p-4 bg-white/5 border border-chess-border rounded-2xl">
                      <div className="flex items-center gap-4">
                        <div className="w-12 h-12 rounded-full bg-chess-bg border border-chess-border flex items-center justify-center font-bold text-chess-muted text-xl">B</div>
                        <div>
                          <p className="font-bold">{match?.black_player?.username}</p>
                          <p className="text-[10px] uppercase font-black text-chess-muted">Pemain Hitam</p>
                        </div>
                      </div>
                      <span className={`font-black uppercase tracking-widest ${match.winner === 'black' ? 'text-chess-green' : 'text-chess-muted'}`}>{match.winner === 'black' ? 'WINNER' : ''}</span>
                    </div>
                  </div>

                  <div className="mt-12 space-y-4">
                    <button 
                      onClick={() => navigate('/room')}
                      className="w-full bg-chess-green py-5 rounded-2xl font-black text-xl hover:scale-[1.02] active:scale-[0.98] transition-all shadow-xl shadow-chess-green/20"
                    >
                      MAIN LAGI
                    </button>
                    <div className="grid grid-cols-2 gap-4">
                      <Link to="/leaderboard" className="flex items-center justify-center gap-2 py-3 bg-white/5 hover:bg-white/10 rounded-xl font-bold text-sm transition-all border border-chess-border">
                        <Trophy className="w-4 h-4 text-yellow-500" /> Leaderboard
                      </Link>
                      <Link to="/home" className="flex items-center justify-center gap-2 py-3 bg-white/5 hover:bg-white/10 rounded-xl font-bold text-sm transition-all border border-chess-border">
                        <CornerUpLeft className="w-4 h-4" /> Beranda
                      </Link>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </motion.div>
        </main>
      </div>
    </div>
  );
};

export default GameResult;
