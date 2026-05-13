import React from 'react';
import { LucideIcon } from 'lucide-react';

interface EmptyStateProps {
  icon?: LucideIcon;
  title: string;
  description: string;
  actionLabel?: string;
  onAction?: () => void;
}

const EmptyState: React.FC<EmptyStateProps> = ({ 
  icon: Icon, 
  title, 
  description, 
  actionLabel, 
  onAction 
}) => {
  return (
    <div className="flex flex-col items-center justify-center p-12 text-center bg-chess-card border border-dashed border-chess-border rounded-xl">
      <div className="w-20 h-20 mb-6 bg-white/5 rounded-full flex items-center justify-center">
        {Icon ? (
          <Icon className="w-10 h-10 text-chess-muted" />
        ) : (
          <span className="text-4xl">♙</span>
        )}
      </div>
      <h3 className="text-xl font-bold mb-2">{title}</h3>
      <p className="text-chess-muted max-w-xs mb-8">{description}</p>
      
      {actionLabel && onAction && (
        <button
          onClick={onAction}
          className="px-6 py-2 bg-chess-green hover:brightness-110 rounded-lg font-bold text-white transition-all shadow-lg shadow-chess-green/20"
        >
          {actionLabel}
        </button>
      )}
    </div>
  );
};

export default EmptyState;
