import React from 'react';
import { Award } from 'lucide-react';

interface PlayerCardProps {
  name: string;
  username: string;
  points: number;
  isActive: boolean;
  isMe?: boolean;
}

const PlayerCard: React.FC<PlayerCardProps> = ({ name, username, points, isActive, isMe }) => {
  return (
    <div className={`
      flex items-center gap-4 p-3 rounded-xl border transition-all
      ${isActive 
        ? 'bg-chess-green/10 border-chess-green/40 shadow-lg shadow-chess-green/5' 
        : 'bg-white/5 border-chess-border'}
    `}>
      <div className={`
        w-12 h-12 rounded-full flex items-center justify-center font-black text-xl
        ${isActive ? 'bg-chess-green text-white' : 'bg-chess-bg border border-chess-border text-chess-muted'}
      `}>
        {username.charAt(0).toUpperCase()}
      </div>
      
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <p className={`font-bold truncate ${isActive ? 'text-white' : 'text-chess-muted'}`}>{name}</p>
          {isMe && <span className="text-[10px] bg-chess-green/20 text-chess-green px-1.5 rounded font-black italic">YOU</span>}
        </div>
        <div className="flex items-center gap-1.5 text-xs text-chess-muted">
          <Award className="w-3 h-3 text-yellow-500" />
          <span>{points} pts</span>
        </div>
      </div>

      {isActive && (
        <div className="w-2 h-2 bg-chess-green rounded-full animate-pulse shadow-[0_0_8px_#81B64C]" />
      )}
    </div>
  );
};

export default PlayerCard;
