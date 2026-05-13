import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { GameProvider, useGame } from '../contexts/GameContext';
import { useAuth } from '../contexts/AuthContext';
import { useNotification } from '../contexts/NotificationContext';
import { useWebSocket } from '../hooks/useWebSocket';
import ChessBoard from '../components/chess/ChessBoard';
import Timer from '../components/ui/Timer';
import PlayerCard from '../components/ui/PlayerCard';
import MoveHistory from '../components/ui/MoveHistory';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import ConfirmDialog from '../components/ui/ConfirmDialog';
import { api } from '../services/api';
import { Flag, Send, RefreshCw, MessageCircle, ChevronRight, Share2 } from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';

const GameContainer: React.FC = () => {
  const { roomId } = useParams();
  const { setGameData, boardState, turn, status, players, selectSquare, selectedSquare, legalMoves, lastMove, checkSquare, makeMove, resetGame } = useGame();
  const { user } = useAuth();
  const { showToast } = useNotification();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(true);
  const [chatMessage, setChatMessage] = useState('');
  const [chats, setChats] = useState<any[]>([]);
  const [resignConfirm, setResignConfirm] = useState(false);
  const [orientation, setOrientation] = useState<'white' | 'black'>('white');
  const [showResultOverlay, setShowResultOverlay] = useState(false);

  const onWSMessage = useCallback((msg: any) => {
    switch (msg.type) {
      case 'move':
        // Update board state from message
        // In a real app, the backend sends the new FEN and pieces
        break;
      case 'chat':
        setChats(prev => [...prev, { from: msg.from, message: msg.message }]);
        break;
      case 'game_end':
        showToast(`Permainan berakhir: ${msg.result}`, 'info');
        setShowResultOverlay(true);
        break;
      case 'opponent_disconnected':
        showToast('Lawan terputus. Menunggu...', 'warning');
        break;
      case 'opponent_reconnected':
        showToast('Lawan terhubung kembali!', 'success');
        break;
    }
  }, [showToast]);

  const { status: wsStatus, sendMessage: wsSend } = useWebSocket(roomId || null, onWSMessage);

  useEffect(() => {
    const fetchInitialData = async () => {
      try {
        const data = await api.get<{ room: any }>(`/room/status/${roomId}`);
        setGameData(data.room);
        
        // Determine orientation
        if (data.room.black_player?.id === user?.id) {
          setOrientation('black');
        } else {
          setOrientation('white');
        }
      } catch (err) {
        showToast('Gagal memuat data permainan', 'error');
        navigate('/home');
      } finally {
        setLoading(false);
      }
    };
    fetchInitialData();
  }, [roomId, setGameData, user, showToast, navigate]);

  const handleSquareClick = async (coord: string) => {
    if (status !== 'playing') return;

    // If a square is already selected and we click a different one
    if (selectedSquare && selectedSquare !== coord) {
      const success = await makeMove(selectedSquare, coord);
      if (success) {
        // Send move to server via WS for real-time sync
        wsSend('move', { from: selectedSquare, to: coord });
        return;
      }
    }
    
    // Otherwise just select the square
    selectSquare(coord);
  };

  const handleSendChat = (e: React.FormEvent) => {
    e.preventDefault();
    if (!chatMessage.trim()) return;
    wsSend('chat', { message: chatMessage });
    setChats(prev => [...prev, { from: 'Anda', message: chatMessage, isMe: true }]);
    setChatMessage('');
  };

  const handleResign = () => {
    wsSend('resign');
    setResignConfirm(false);
  };

  if (loading) return <LoadingSpinner />;

  const isMyTurn = (turn === 'white' && user?.id === players.white?.id) ||
                   (turn === 'black' && user?.id === players.black?.id);

  const whitePlayer = players.white || { name: 'Menunggu...', username: 'waiting', points: 0 };
  const blackPlayer = players.black || { name: 'Menunggu...', username: 'waiting', points: 0 };

  const topPlayer = orientation === 'white' ? blackPlayer : whitePlayer;
  const bottomPlayer = orientation === 'white' ? whitePlayer : blackPlayer;

  return (
    <div className="min-h-screen bg-chess-bg text-chess-text flex flex-col p-4 sm:p-8">
      {/* Header */}
      <div className="flex items-center justify-between mb-8 max-w-[1200px] mx-auto w-full">
        <button onClick={() => navigate('/home')} className="flex items-center gap-2 text-chess-muted hover:text-white transition-all group">
          <span className="text-2xl group-hover:scale-110 transition-transform">♞</span>
          <span className="font-bold hidden sm:block">CHESSID</span>
        </button>

        <div className="flex items-center gap-4">
          <div className="flex items-center gap-2 px-3 py-1 bg-white/5 rounded-full border border-chess-border text-[10px] font-black uppercase tracking-widest">
            <div className={`w-2 h-2 rounded-full ${wsStatus === 'connected' ? 'bg-chess-green animate-pulse' : 'bg-red-500'}`} />
            {wsStatus}
          </div>
          <button onClick={() => setOrientation(o => o === 'white' ? 'black' : 'white')} className="p-2 hover:bg-white/5 rounded-lg text-chess-muted hover:text-white" title="Flip Board">
            <RefreshCw className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* Main Game Layout */}
      <div className="flex-1 flex flex-col lg:flex-row gap-8 max-w-[1200px] mx-auto w-full items-start justify-center">
        
        {/* Left Side: Board and Player Info */}
        <div className="flex-1 flex flex-col gap-4 w-full max-w-[600px]">
          {/* Top Player (Opponent) */}
          <div className="flex items-center justify-between">
            <PlayerCard 
              name={topPlayer.name} 
              username={topPlayer.username} 
              points={topPlayer.points} 
              isActive={turn === (orientation === 'white' ? 'black' : 'white')}
            />
            <Timer initialSeconds={600} isActive={turn === (orientation === 'white' ? 'black' : 'white')} />
          </div>

          {/* Board */}
          <div className="relative group">
            <ChessBoard 
              boardState={boardState}
              onSquareClick={handleSquareClick}
              selectedSquare={selectedSquare}
              legalMoves={legalMoves}
              lastMove={lastMove}
              checkSquare={checkSquare}
              orientation={orientation}
            />
            
            {status === 'waiting' && (
              <div className="absolute inset-0 bg-black/60 backdrop-blur-sm flex flex-col items-center justify-center p-6 text-center animate-fade-in z-20">
                <div className="w-16 h-16 border-4 border-chess-green border-t-transparent rounded-full animate-spin mb-4" />
                <h3 className="text-xl font-bold mb-2">Menunggu Lawan...</h3>
                <p className="text-sm text-chess-muted mb-6">Bagikan kode room untuk mengajak teman bermain.</p>
                <button className="flex items-center gap-2 px-6 py-2 bg-white/10 hover:bg-white/20 rounded-xl text-sm font-bold transition-all">
                  <Share2 className="w-4 h-4" /> SALIN KODE
                </button>
              </div>
            )}
          </div>

          {/* Bottom Player (Self) */}
          <div className="flex items-center justify-between">
            <PlayerCard 
              name={bottomPlayer.name} 
              username={bottomPlayer.username} 
              points={bottomPlayer.points} 
              isActive={turn === orientation}
              isMe={bottomPlayer.id === user?.id}
            />
            <Timer initialSeconds={600} isActive={turn === orientation} />
          </div>
        </div>

        {/* Right Side: Panels */}
        <div className="w-full lg:w-[350px] flex flex-col gap-4 h-full lg:max-h-[600px]">
          {/* Move History */}
          <div className="h-48 lg:h-1/2">
            <MoveHistory moves={[]} />
          </div>

          {/* Chat Panel */}
          <div className="flex-1 flex flex-col bg-chess-card border border-chess-border rounded-xl overflow-hidden h-64 lg:h-1/2">
            <div className="p-3 border-b border-chess-border flex items-center gap-2 bg-white/5">
              <MessageCircle className="w-4 h-4 text-chess-green" />
              <h3 className="text-xs font-black uppercase tracking-widest text-chess-muted">Chat</h3>
            </div>

            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {chats.map((chat, i) => (
                <div key={i} className={`flex flex-col ${chat.isMe ? 'items-end' : 'items-start'}`}>
                  <span className="text-[10px] text-chess-muted mb-1 font-bold">{chat.from}</span>
                  <div className={`px-3 py-2 rounded-2xl text-xs max-w-[80%] ${chat.isMe ? 'bg-chess-green text-white rounded-tr-none' : 'bg-white/5 text-chess-text rounded-tl-none'}`}>
                    {chat.message}
                  </div>
                </div>
              ))}
              {chats.length === 0 && <p className="text-[10px] text-center text-chess-muted mt-8 uppercase tracking-tighter">Mulai percakapan...</p>}
            </div>

            <form onSubmit={handleSendChat} className="p-2 border-t border-chess-border bg-chess-bg flex gap-2">
              <input 
                type="text" 
                value={chatMessage}
                onChange={(e) => setChatMessage(e.target.value)}
                placeholder="Tulis pesan..."
                className="flex-1 bg-white/5 border border-chess-border rounded-lg px-3 py-2 text-xs outline-none focus:border-chess-green transition-all"
              />
              <button type="submit" className="p-2 bg-chess-green hover:brightness-110 rounded-lg text-white transition-all">
                <Send className="w-4 h-4" />
              </button>
            </form>
          </div>

          {/* Controls */}
          <div className="flex gap-2">
            <button className="flex-1 py-3 bg-white/5 hover:bg-white/10 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2">
              <RefreshCw className="w-4 h-4" /> AJAK SERI
            </button>
            <button 
              onClick={() => setResignConfirm(true)}
              className="flex-1 py-3 bg-red-500/10 text-red-500 hover:bg-red-500/20 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2"
            >
              <Flag className="w-4 h-4" /> MENYERAH
            </button>
          </div>
        </div>
      </div>

      {/* Result Overlay */}
      <AnimatePresence>
        {showResultOverlay && (
          <motion.div 
            initial={{ opacity: 0 }} 
            animate={{ opacity: 1 }} 
            className="fixed inset-0 z-[100] bg-black/80 backdrop-blur-md flex items-center justify-center p-4"
          >
            <motion.div 
              initial={{ scale: 0.8, y: 20 }} 
              animate={{ scale: 1, y: 0 }} 
              className="bg-chess-card border-4 border-chess-green rounded-3xl p-12 text-center max-w-sm w-full shadow-[0_0_50px_#81B64C33]"
            >
              <h2 className="text-4xl font-black mb-2 italic">SKAK MAT!</h2>
              <p className="text-chess-green text-2xl font-black mb-8 italic uppercase">Anda Menang!</p>
              
              <div className="flex items-center justify-center gap-4 mb-8">
                <div className="text-center">
                  <p className="text-xs text-chess-muted font-bold uppercase">Poin</p>
                  <p className="text-3xl font-black text-chess-green">+15</p>
                </div>
                <div className="w-px h-10 bg-chess-border" />
                <div className="text-center">
                  <p className="text-xs text-chess-muted font-bold uppercase">Total</p>
                  <p className="text-3xl font-black">1015</p>
                </div>
              </div>

              <div className="space-y-3">
                <button 
                  onClick={() => navigate('/home')}
                  className="w-full bg-chess-green py-4 rounded-2xl font-black text-xl hover:scale-105 transition-all shadow-lg shadow-chess-green/20"
                >
                  SELESAI
                </button>
                <button className="w-full py-3 text-chess-muted hover:text-white font-bold transition-all">
                  REMATCH?
                </button>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>

      <ConfirmDialog 
        isOpen={resignConfirm}
        onClose={() => setResignConfirm(false)}
        onConfirm={handleResign}
        title="Apakah Anda yakin?"
        message="Menyerah akan mengakhiri permainan dan poin Anda akan berkurang."
        confirmLabel="Ya, Saya Menyerah"
        isDestructive
      />
    </div>
  );
};

const Game: React.FC = () => (
  <GameProvider>
    <GameContainer />
  </GameProvider>
);

export default Game;
