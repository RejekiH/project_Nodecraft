import React, { createContext, useContext, useState, useEffect, useReducer, useCallback } from 'react';
import { STORAGE_KEY } from '../utils/constants';
import { api } from '../services/api';

interface User {
  id: number;
  name: string;
  username: string;
  points: number;
  role: 'user' | 'admin';
  total_match?: number;
  win?: number;
  loss?: number;
}

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isAdmin: boolean;
  loading: boolean;
  error: string | null;
}

type AuthAction = 
  | { type: 'LOGIN_START' }
  | { type: 'LOGIN_SUCCESS'; payload: { user: User; token: string } }
  | { type: 'LOGIN_FAILURE'; payload: string }
  | { type: 'LOGOUT' }
  | { type: 'UPDATE_USER'; payload: User }
  | { type: 'SET_LOADING'; payload: boolean };

const initialState: AuthState = {
  user: null,
  token: localStorage.getItem(STORAGE_KEY),
  isAuthenticated: false,
  isAdmin: false,
  loading: true,
  error: null,
};

function authReducer(state: AuthState, action: AuthAction): AuthState {
  switch (action.type) {
    case 'LOGIN_START':
      return { ...state, loading: true, error: null };
    case 'LOGIN_SUCCESS':
      return {
        ...state,
        user: action.payload.user,
        token: action.payload.token,
        isAuthenticated: true,
        isAdmin: action.payload.user.role === 'admin',
        loading: false,
        error: null,
      };
    case 'LOGIN_FAILURE':
      return { ...state, loading: false, error: action.payload, isAuthenticated: false };
    case 'LOGOUT':
      return { 
        ...state, 
        user: null, 
        token: null, 
        isAuthenticated: false, 
        isAdmin: false, 
        loading: false 
      };
    case 'UPDATE_USER':
      return { ...state, user: action.payload, isAdmin: action.payload.role === 'admin' };
    case 'SET_LOADING':
      return { ...state, loading: action.payload };
    default:
      return state;
  }
}

interface AuthContextType extends AuthState {
  login: (token: string, user: User) => void;
  logout: () => void;
  updateUser: (user: User) => void;
  refreshProfile: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [state, dispatch] = useReducer(authReducer, initialState);

  const refreshProfile = useCallback(async () => {
    const token = localStorage.getItem(STORAGE_KEY);
    if (!token) {
      dispatch({ type: 'SET_LOADING', payload: false });
      return;
    }

    try {
      const data = await api.get<{ user: User }>('/user/profile');
      dispatch({ type: 'LOGIN_SUCCESS', payload: { user: data.user, token } });
    } catch (err) {
      console.error('Failed to validate token', err);
      localStorage.removeItem(STORAGE_KEY);
      dispatch({ type: 'LOGOUT' });
    }
  }, []);

  useEffect(() => {
    refreshProfile();
  }, [refreshProfile]);

  const login = (token: string, user: User) => {
    localStorage.setItem(STORAGE_KEY, token);
    dispatch({ type: 'LOGIN_SUCCESS', payload: { user, token } });
  };

  const logout = () => {
    localStorage.removeItem(STORAGE_KEY);
    dispatch({ type: 'LOGOUT' });
  };

  const updateUser = (user: User) => {
    dispatch({ type: 'UPDATE_USER', payload: user });
  };

  return (
    <AuthContext.Provider value={{ ...state, login, logout, updateUser, refreshProfile }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
