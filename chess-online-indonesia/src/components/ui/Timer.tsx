import React, { useState, useEffect } from 'react';
import { Clock } from 'lucide-react';

interface TimerProps {
  initialSeconds: number;
  isActive: boolean;
  onTimeout?: () => void;
}

const Timer: React.FC<TimerProps> = ({ initialSeconds, isActive, onTimeout }) => {
  const [seconds, setSeconds] = useState(initialSeconds);

  useEffect(() => {
    setSeconds(initialSeconds);
  }, [initialSeconds]);

  useEffect(() => {
    let interval: any = null;
    if (isActive && seconds > 0) {
      interval = setInterval(() => {
        setSeconds((s) => s - 1);
      }, 1000);
    } else if (seconds === 0 && isActive) {
      if (onTimeout) onTimeout();
      clearInterval(interval);
    }
    return () => clearInterval(interval);
  }, [isActive, seconds, onTimeout]);

  const formatTime = (s: number) => {
    const mins = Math.floor(s / 60);
    const secs = s % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  const isLowTime = seconds < 30;

  return (
    <div className={`
      flex items-center gap-2 px-4 py-2 rounded-lg font-mono text-xl sm:text-2xl font-bold transition-colors
      ${isActive 
        ? isLowTime ? 'bg-red-500 text-white animate-pulse' : 'bg-chess-green text-white shadow-lg shadow-chess-green/20' 
        : 'bg-white/5 text-chess-muted'}
    `}>
      <Clock className={`w-5 h-5 ${isActive ? 'animate-spin-slow' : ''}`} />
      {formatTime(seconds)}
    </div>
  );
};

export default Timer;
