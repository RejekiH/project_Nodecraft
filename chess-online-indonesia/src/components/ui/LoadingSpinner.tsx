import React from 'react';

const LoadingSpinner: React.FC = () => {
  return (
    <div className="flex items-center justify-center min-h-[400px] w-full bg-chess-bg">
      <div className="relative w-16 h-16">
        <div className="absolute top-0 left-0 w-full h-full border-4 border-chess-green border-t-transparent rounded-full animate-spin"></div>
        <div className="absolute top-2 left-2 w-12 h-12 border-4 border-chess-muted border-b-transparent rounded-full animate-spin-slow opacity-50"></div>
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="text-2xl animate-pulse">♞</span>
        </div>
      </div>
    </div>
  );
};

export default LoadingSpinner;
