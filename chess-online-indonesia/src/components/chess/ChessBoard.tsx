import React from 'react';
import ChessSquare from './ChessSquare';
import { getCoord, getSquareColor, BoardState, PieceSymbol } from '../../utils/boardUtils';
import { BOARD_THEMES } from '../../utils/constants';

interface ChessBoardProps {
  boardState: BoardState;
  onSquareClick: (coord: string) => void;
  selectedSquare: string | null;
  legalMoves: string[];
  lastMove: { from: string; to: string } | null;
  checkSquare: string | null;
  orientation: 'white' | 'black';
  themeKey?: keyof typeof BOARD_THEMES;
}

const ChessBoard: React.FC<ChessBoardProps> = ({
  boardState,
  onSquareClick,
  selectedSquare,
  legalMoves,
  lastMove,
  checkSquare,
  orientation,
  themeKey = 'CLASSIC'
}) => {
  const theme = BOARD_THEMES[themeKey];
  
  // Define indices based on orientation
  const fileIndices = orientation === 'white' ? [0, 1, 2, 3, 4, 5, 6, 7] : [7, 6, 5, 4, 3, 2, 1, 0];
  const rankIndices = orientation === 'white' ? [0, 1, 2, 3, 4, 5, 6, 7] : [7, 6, 5, 4, 3, 2, 1, 0];

  return (
    <div className="relative aspect-square w-full max-w-[600px] border-4 border-chess-border shadow-2xl rounded-sm overflow-hidden select-none">
      <div className="grid grid-cols-8 grid-rows-8 w-full h-full">
        {rankIndices.map((rankIdx) => (
          <React.Fragment key={rankIdx}>
            {fileIndices.map((fileIdx) => {
              const coord = getCoord(fileIdx, rankIdx);
              const piece = boardState[coord];
              const color = getSquareColor(fileIdx, rankIdx);
              
              const isSelected = selectedSquare === coord;
              const isLastMove = lastMove?.from === coord || lastMove?.to === coord;
              const isCheck = checkSquare === coord;
              const isLegalMove = legalMoves.includes(coord);

              // Logic for showing coordinates
              const showCoords = {
                file: rankIdx === (orientation === 'white' ? 7 : 0) ? coord[0] : undefined,
                rank: fileIdx === (orientation === 'white' ? 0 : 7) ? parseInt(coord[1]) : undefined
              };

              return (
                <ChessSquare
                  key={coord}
                  id={coord}
                  color={color}
                  piece={piece}
                  selected={isSelected}
                  isLastMove={isLastMove}
                  isCheck={isCheck}
                  isLegalMove={isLegalMove}
                  onClick={() => onSquareClick(coord)}
                  showCoordinates={showCoords.file || showCoords.rank ? showCoords : null}
                  theme={theme}
                />
              );
            })}
          </React.Fragment>
        ))}
      </div>
    </div>
  );
};

export default ChessBoard;
