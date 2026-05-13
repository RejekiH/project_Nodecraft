/**
 * Utilities for Chess Board Logic
 */

import { CHESS_PIECES } from './constants';

export type PieceSymbol = 'p' | 'r' | 'n' | 'b' | 'q' | 'k' | 'P' | 'R' | 'N' | 'B' | 'Q' | 'K' | null;
export type SquareId = string; // e.g. "e4"

export interface BoardState {
  [square: string]: PieceSymbol;
}

/**
 * Converts FEN string to BoardState object.
 * Simple implementation for visualization.
 */
export const parseFen = (fen: string): BoardState => {
  const [position] = fen.split(' ');
  const rows = position.split('/');
  const board: BoardState = {};

  rows.forEach((row, rowIndex) => {
    let colIndex = 0;
    for (const char of row) {
      if (isNaN(parseInt(char))) {
        const file = String.fromCharCode(97 + colIndex);
        const rank = 8 - rowIndex;
        board[`${file}${rank}`] = char as PieceSymbol;
        colIndex++;
      } else {
        colIndex += parseInt(char);
      }
    }
  });

  return board;
};

/**
 * Gets Unicode symbol for a piece character.
 */
export const getPieceSymbol = (char: PieceSymbol): string => {
  if (!char) return '';
  const isWhite = char === char.toUpperCase();
  const type = char.toUpperCase();
  const set = isWhite ? CHESS_PIECES.WHITE : CHESS_PIECES.BLACK;

  switch (type) {
    case 'K': return set.KING;
    case 'Q': return set.QUEEN;
    case 'R': return set.ROOK;
    case 'B': return set.BISHOP;
    case 'N': return set.KNIGHT;
    case 'P': return set.PAWN;
    default: return '';
  }
};

/**
 * Gets color of square (0,0 = light, 1,0 = dark etc)
 */
export const getSquareColor = (fileIndex: number, rankIndex: number): 'light' | 'dark' => {
  return (fileIndex + rankIndex) % 2 === 0 ? 'light' : 'dark';
};

/**
 * Gets coordinate from index.
 */
export const getCoord = (fileIdx: number, rankIdx: number): SquareId => {
  const file = String.fromCharCode(97 + fileIdx);
  const rank = 8 - rankIdx;
  return `${file}${rank}`;
};

/**
 * Initial board setup FEN.
 */
export const INITIAL_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
