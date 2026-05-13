import React, { createContext, useContext, useState, useReducer, useCallback, useEffect } from 'react';
import { parseFen, BoardState, INITIAL_FEN, PieceSymbol } from '../utils/boardUtils';
import { api } from '../services/api';
import { useAuth } from './AuthContext';
import { useNotification } from './NotificationContext';

interface GameState {
  roomId: string | null;
  boardState: BoardState;
  turn: 'white' | 'black' | null;
  myColor: 'white' | 'black' | null;
  status: 'waiting' | 'playing' | 'finished' | 'draw';
  selectedSquare: string | null;
  legalMoves: string[];
  moveHistory: string[];
  lastMove: { from: string; to: string } | null;
  checkSquare: string | null;
  players: {
    white: any;
    black: any;
  };
  gameResult: string | null;
}

type GameAction = 
  | { type: 'SET_GAME_DATA'; payload: any }
  | { type: 'SELECT_SQUARE'; payload: { coord: string, legalMoves: string[] } }
  | { type: 'DESELECT_SQUARE' }
  | { type: 'MAKE_MOVE'; payload: { from: string, to: string, fen: string, turn: string } }
  | { type: 'SET_WS_MOVE'; payload: { from: string, to: string, fen: string, turn: string } }
  | { type: 'SET_STATUS'; payload: string }
  | { type: 'RESET_GAME' };

const initialState: GameState = {
  roomId: null,
  boardState: parseFen(INITIAL_FEN),
  turn: 'white',
  myColor: null,
  status: 'waiting',
  selectedSquare: null,
  legalMoves: [],
  moveHistory: [],
  lastMove: null,
  checkSquare: null,
  players: { white: null, black: null },
  gameResult: null,
};

function gameReducer(state: GameState, action: GameAction): GameState {
  switch (action.type) {
    case 'SET_GAME_DATA':
      return {
        ...state,
        roomId: action.payload.id,
        boardState: parseFen(action.payload.board_state || INITIAL_FEN),
        turn: action.payload.current_turn || 'white',
        status: action.payload.status,
        players: {
          white: action.payload.white_player,
          black: action.payload.black_player
        }
      };
    case 'SELECT_SQUARE':
      return { ...state, selectedSquare: action.payload.coord, legalMoves: action.payload.legalMoves };
    case 'DESELECT_SQUARE':
      return { ...state, selectedSquare: null, legalMoves: [] };
    case 'MAKE_MOVE':
      return {
        ...state,
        boardState: parseFen(action.payload.fen),
        turn: action.payload.turn as any,
        lastMove: { from: action.payload.from, to: action.payload.to },
        selectedSquare: null,
        legalMoves: [],
        moveHistory: [...state.moveHistory, `${action.payload.from}-${action.payload.to}`]
      };
    case 'SET_WS_MOVE':
      return {
        ...state,
        boardState: parseFen(action.payload.fen),
        turn: action.payload.turn as any,
        lastMove: { from: action.payload.from, to: action.payload.to },
        moveHistory: [...state.moveHistory, `${action.payload.from}-${action.payload.to}`]
      };
    case 'SET_STATUS':
      return { ...state, status: action.payload as any };
    case 'RESET_GAME':
      return initialState;
    default:
      return state;
  }
}

interface GameContextType extends GameState {
  setGameData: (data: any) => void;
  selectSquare: (coord: string) => void;
  makeMove: (from: string, to: string, promotion?: string) => Promise<boolean>;
  resetGame: () => void;
}

const GameContext = createContext<GameContextType | undefined>(undefined);

export const GameProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [state, dispatch] = useReducer(gameReducer, initialState);
  const { user } = useAuth();
  const { showToast } = useNotification();

  const setGameData = useCallback((data: any) => {
    dispatch({ type: 'SET_GAME_DATA', payload: data });
  }, []);

  const selectSquare = useCallback(async (coord: string) => {
    // Basic logic: if clicking already selected square, deselect
    if (state.selectedSquare === coord) {
      dispatch({ type: 'DESELECT_SQUARE' });
      return;
    }

    const piece = state.boardState[coord];
    const isPlayerTurn = (state.turn === 'white' && user?.id === state.players.white?.id) ||
                         (state.turn === 'black' && user?.id === state.players.black?.id);

    if (piece && isPlayerTurn) {
      // In a real app, you'd fetch legal moves from backend or use a library
      // For now, we'll just select it
      dispatch({ type: 'SELECT_SQUARE', payload: { coord, legalMoves: [] } });
    } else if (state.selectedSquare) {
      // Trying to move to 'coord'
      // This will be handled by calling makeMove from the component
    }
  }, [state.selectedSquare, state.boardState, state.turn, state.players, user]);

  const makeMove = useCallback(async (from: string, to: string, promotion?: string) => {
    try {
      const data = await api.post<{ valid: boolean, board_state: string, turn: string }>('/gameplay/validate-move', {
        room_id: state.roomId,
        from,
        to,
        promotion
      });

      if (data.valid) {
        dispatch({ type: 'MAKE_MOVE', payload: { from, to, fen: data.board_state, turn: data.turn } });
        return true;
      }
      return false;
    } catch (err: any) {
      showToast(err.message || 'Langkah tidak valid', 'error');
      return false;
    }
  }, [state.roomId, showToast]);

  const resetGame = useCallback(() => {
    dispatch({ type: 'RESET_GAME' });
  }, []);

  return (
    <GameContext.Provider value={{ ...state, setGameData, selectSquare, makeMove, resetGame }}>
      {children}
    </GameContext.Provider>
  );
};

export const useGame = () => {
  const context = useContext(GameContext);
  if (context === undefined) {
    throw new Error('useGame must be used within a GameProvider');
  }
  return context;
};
