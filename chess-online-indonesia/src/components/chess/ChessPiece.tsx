import React from 'react';
import { getPieceSymbol, PieceSymbol } from '../../utils/boardUtils';
import { motion } from 'motion/react';

interface ChessPieceProps {
  type: PieceSymbol;
  onClick?: () => void;
}

const ChessPiece: React.FC<ChessPieceProps> = ({ type, onClick }) => {
  if (!type) return null;

  return (
    <motion.div
      layoutId={type + Math.random()} // Simplified layout animation
      initial={{ scale: 0.8, opacity: 0 }}
      animate={{ scale: 1, opacity: 1 }}
      className="w-full h-full flex items-center justify-center cursor-grab active:cursor-grabbing select-none"
      onClick={(e) => {
        if (onClick) {
          e.stopPropagation();
          onClick();
        }
      }}
    >
      <span className="text-4xl sm:text-5xl md:text-6xl drop-shadow-md">
        {getPieceSymbol(type)}
      </span>
    </motion.div>
  );
};

export default ChessPiece;
