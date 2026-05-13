import React from 'react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import ChessPiece from './ChessPiece';
import { PieceSymbol } from '../../utils/boardUtils';

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

interface ChessSquareProps {
  id: string;
  color: 'light' | 'dark';
  piece: PieceSymbol;
  selected: boolean;
  isLastMove: boolean;
  isCheck: boolean;
  isLegalMove: boolean;
  onClick: () => void;
  showCoordinates: { file?: string; rank?: number } | null;
  theme: { light: string; dark: string };
}

const ChessSquare: React.FC<ChessSquareProps> = ({
  id,
  color,
  piece,
  selected,
  isLastMove,
  isCheck,
  isLegalMove,
  onClick,
  showCoordinates,
  theme
}) => {
  return (
    <div
      onClick={onClick}
      className={cn(
        "relative w-full aspect-square flex items-center justify-center transition-colors cursor-pointer",
        color === 'light' ? theme.light : theme.dark,
        selected && "bg-chess-highlight/60",
        isLastMove && "bg-chess-highlight/30",
        isCheck && "bg-red-500/40"
      )}
    >
      {/* Piece */}
      {piece && <ChessPiece type={piece} />}

      {/* Legal Move Indicator */}
      {isLegalMove && (
        <div className={cn(
          "absolute rounded-full",
          piece ? "inset-0 border-[6px] border-black/10" : "w-1/4 h-1/4 bg-black/10"
        )} />
      )}

      {/* Coordinates */}
      {showCoordinates?.file && (
        <span className={cn(
          "absolute bottom-0.5 right-1 text-[10px] font-bold select-none",
          color === 'light' ? "text-chess-dark-sq" : "text-chess-light-sq"
        )}>
          {showCoordinates.file}
        </span>
      )}
      {showCoordinates?.rank && (
        <span className={cn(
          "absolute top-0.5 left-1 text-[10px] font-bold select-none",
          color === 'light' ? "text-chess-dark-sq" : "text-chess-light-sq"
        )}>
          {showCoordinates.rank}
        </span>
      )}
    </div>
  );
};

export default ChessSquare;
