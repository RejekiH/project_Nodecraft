/**
 * Constants for the Chess Online application.
 */

export const API_BASE_URL = 'http://localhost:8000/api';
export const WS_BASE_URL = 'ws://localhost:8000/ws';

export const STORAGE_KEY = 'chess_token';

export const CHESS_PIECES = {
  WHITE: {
    KING: '♔',
    QUEEN: '♕',
    ROOK: '♖',
    BISHOP: '♗',
    KNIGHT: '♘',
    PAWN: '♙',
  },
  BLACK: {
    KING: '♚',
    QUEEN: '♛',
    ROOK: '♜',
    BISHOP: '♝',
    KNIGHT: '♞',
    PAWN: '♟',
  },
};

export const BOARD_THEMES = {
  CLASSIC: {
    dark: 'bg-chess-dark-sq',
    light: 'bg-chess-light-sq',
  },
  GREEN: {
    dark: 'bg-chess-green-sq',
    light: 'bg-chess-green-l-sq',
  },
};

export const GAME_STATUS = {
  WAITING: 'waiting',
  PLAYING: 'playing',
  FINISHED: 'finished',
  DRAW: 'draw',
};

export const POINTS = {
  WIN: 15,
  LOSS: -5,
  DRAW: 0,
};

export const QUOTES = [
  "Catur adalah segalanya: seni, sains, dan olahraga.",
  "Bahkan pion yang paling kecil pun bisa mengubah jalannya permainan.",
  "Dalam catur, seperti dalam kehidupan, kemenangan berawal dari kesalahan lawan.",
  "Satu langkah buruk menghancurkan empat puluh langkah baik.",
  "Pemenang catur adalah orang yang membuat kesalahan nomor dua terakhir.",
];
