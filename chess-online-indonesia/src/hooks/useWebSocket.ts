import { useEffect, useRef, useState, useCallback } from 'react';
import { WS_BASE_URL, STORAGE_KEY } from '../utils/constants';

interface WSMessage {
  type: string;
  [key: string]: any;
}

export const useWebSocket = (roomId: string | null, onMessage: (msg: WSMessage) => void) => {
  const [status, setStatus] = useState<'connecting' | 'connected' | 'disconnected' | 'reconnecting'>('disconnected');
  const ws = useRef<WebSocket | null>(null);
  const reconnectCount = useRef(0);
  const maxReconnects = 5;

  const connect = useCallback(() => {
    if (!roomId) return;

    const token = localStorage.getItem(STORAGE_KEY);
    setStatus('connecting');
    
    // Using roomId in path, though browser WS might not allow custom headers
    // Backend typically handles auth via query param or subprotocol
    const socket = new WebSocket(`${WS_BASE_URL}/room/${roomId}?token=${token}`);

    socket.onopen = () => {
      console.log('WS Connected');
      setStatus('connected');
      reconnectCount.current = 0;
    };

    socket.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        onMessage(data);
      } catch (err) {
        console.error('Error parsing WS message', err);
      }
    };

    socket.onclose = () => {
      console.log('WS Disconnected');
      setStatus('disconnected');
      
      if (reconnectCount.current < maxReconnects) {
        const timeout = Math.pow(2, reconnectCount.current) * 1000;
        console.log(`Reconnecting in ${timeout}ms...`);
        setStatus('reconnecting');
        setTimeout(() => {
          reconnectCount.current++;
          connect();
        }, timeout);
      }
    };

    socket.onerror = (err) => {
      console.error('WS Error', err);
      socket.close();
    };

    ws.current = socket;
  }, [roomId, onMessage]);

  useEffect(() => {
    connect();
    return () => {
      if (ws.current) {
        ws.current.close();
      }
    };
  }, [connect]);

  const sendMessage = useCallback((type: string, payload: any = {}) => {
    if (ws.current && ws.current.readyState === WebSocket.OPEN) {
      const token = localStorage.getItem(STORAGE_KEY);
      ws.current.send(JSON.stringify({ type, ...payload, token }));
      return true;
    }
    return false;
  }, []);

  return { status, sendMessage };
};
