import React from 'react';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

interface SkeletonProps {
  className?: string;
}

const LoadingSkeleton: React.FC<SkeletonProps> = ({ className }) => {
  return (
    <div className={cn("animate-pulse bg-chess-card/50 rounded-md", className)} />
  );
};

export const TableSkeleton: React.FC<{ rows?: number }> = ({ rows = 5 }) => {
  return (
    <div className="w-full space-y-4">
      <div className="flex space-x-4">
        {[...Array(4)].map((_, i) => (
          <LoadingSkeleton key={i} className="h-10 flex-1" />
        ))}
      </div>
      {[...Array(rows)].map((_, i) => (
        <LoadingSkeleton key={i} className="h-12 w-full" />
      ))}
    </div>
  );
};

export const CardSkeleton: React.FC = () => {
  return (
    <div className="p-4 border border-chess-border bg-chess-card rounded-lg space-y-3">
      <LoadingSkeleton className="h-6 w-1/3" />
      <LoadingSkeleton className="h-20 w-full" />
      <div className="flex justify-between">
        <LoadingSkeleton className="h-4 w-1/4" />
        <LoadingSkeleton className="h-4 w-1/4" />
      </div>
    </div>
  );
};

export default LoadingSkeleton;
