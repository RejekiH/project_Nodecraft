import React, { useEffect, useRef } from 'react';

interface MoveHistoryProps {
  moves: string[]; // ['e4', 'e5', 'Nf3', 'Nc6', ...]
}

const MoveHistory: React.FC<MoveHistoryProps> = ({ moves }) => {
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [moves]);

  // Group moves by pairs [white, black]
  const pairs: (string | null)[][] = [];
  for (let i = 0; i < moves.length; i += 2) {
    pairs.push([moves[i], moves[i + 1] || null]);
  }

  return (
    <div className="bg-chess-card border border-chess-border rounded-xl flex flex-col h-full overflow-hidden">
      <div className="p-3 border-b border-chess-border bg-white/5">
        <h3 className="text-xs font-black uppercase tracking-widest text-chess-muted">Riwayat Langkah</h3>
      </div>
      
      <div ref={scrollRef} className="flex-1 overflow-y-auto p-2 scroll-smooth">
        {pairs.length > 0 ? (
          <div className="grid grid-cols-[30px_1fr_1fr] gap-x-2 gap-y-1">
            {pairs.map((pair, index) => (
              <React.Fragment key={index}>
                <div className="text-[10px] font-bold text-chess-muted flex items-center justify-center bg-chess-bg rounded">
                  {index + 1}.
                </div>
                <div className="p-2 text-sm font-bold bg-white/5 rounded hover:bg-white/10 transition-colors">
                  {pair[0]}
                </div>
                <div className={`p-2 text-sm font-bold bg-white/5 rounded hover:bg-white/10 transition-colors ${!pair[1] ? 'invisible' : ''}`}>
                  {pair[1] || ''}
                </div>
              </React.Fragment>
            ))}
          </div>
        ) : (
          <div className="h-full flex flex-col items-center justify-center p-6 text-center text-chess-muted">
            <span className="text-4xl opacity-10 mb-2">♙</span>
            <p className="text-[10px] font-bold uppercase tracking-widest leading-tight">Belum ada langkah</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default MoveHistory;
