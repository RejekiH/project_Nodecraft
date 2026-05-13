import React, { useEffect, useState } from 'react';
import { useNotification, ToastType } from '../../contexts/NotificationContext';
import { X, CheckCircle, AlertCircle, Info, AlertTriangle } from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';

const icons: Record<ToastType, React.ReactNode> = {
  success: <CheckCircle className="w-5 h-5 text-green-500" />,
  error: <AlertCircle className="w-5 h-5 text-red-500" />,
  info: <Info className="w-5 h-5 text-blue-500" />,
  warning: <AlertTriangle className="w-5 h-5 text-yellow-500" />,
};

const bgColors: Record<ToastType, string> = {
  success: 'bg-green-500/10 border-green-500/20',
  error: 'bg-red-500/10 border-red-500/20',
  info: 'bg-blue-500/10 border-blue-500/20',
  warning: 'bg-yellow-500/10 border-yellow-500/20',
};

const ToastContainer: React.FC = () => {
  const { toasts, removeToast } = useNotification();

  return (
    <div className="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
      <AnimatePresence>
        {toasts.map((toast) => (
          <motion.div
            key={toast.id}
            initial={{ opacity: 0, x: 20, scale: 0.95 }}
            animate={{ opacity: 1, x: 0, scale: 1 }}
            exit={{ opacity: 0, x: 20, scale: 0.95 }}
            className={`
              pointer-events-auto flex items-start gap-3 p-4 min-w-[300px] max-w-sm rounded-lg border shadow-xl
              bg-chess-card text-chess-text ${bgColors[toast.type]}
            `}
          >
            <div className="mt-0.5">{icons[toast.type]}</div>
            <div className="flex-1 text-sm font-medium">{toast.message}</div>
            <button
              onClick={() => removeToast(toast.id)}
              className="mt-0.5 text-chess-muted hover:text-chess-text transition-colors"
            >
              <X className="w-4 h-4" />
            </button>
          </motion.div>
        ))}
      </AnimatePresence>
    </div>
  );
};

export default ToastContainer;
