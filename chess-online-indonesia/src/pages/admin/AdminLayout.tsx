import React from 'react';
import { Outlet } from 'react-router-dom';
import AdminSidebar from '../../components/layout/AdminSidebar';
import { useAuth } from '../../contexts/AuthContext';
import { LogOut, User as UserIcon } from 'lucide-react';

const AdminLayout: React.FC = () => {
  const { user } = useAuth();

  return (
    <div className="flex h-screen bg-chess-bg text-chess-text overflow-hidden">
      <AdminSidebar />
      
      <div className="flex-1 flex flex-col min-w-0">
        {/* Admin Header */}
        <header className="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between flex-shrink-0">
          <div className="flex items-center gap-2">
             <span className="text-xs font-bold uppercase text-slate-400 tracking-[0.2em]">Dashboard :: </span>
             <span className="text-sm font-bold text-slate-800 uppercase tracking-widest">{location.pathname.split('/').pop()?.replace(/-/g, ' ')}</span>
          </div>

          <div className="flex items-center gap-6">
             <div className="flex items-center gap-3 pr-6 border-r border-slate-100">
                <div className="text-right">
                   <p className="text-xs font-bold text-slate-900 leading-none mb-1">{user?.name}</p>
                   <p className="text-[10px] uppercase font-black text-chess-indigo">SUPER ADMIN</p>
                </div>
                <div className="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center font-bold">
                   <UserIcon className="w-5 h-5 text-chess-indigo" />
                </div>
             </div>
             <button className="text-slate-400 hover:text-red-500 transition-colors p-2 hover:bg-red-50 rounded-lg" title="Logout Admin">
                <LogOut className="w-5 h-5" />
             </button>
          </div>
        </header>

        {/* Admin Content */}
        <main className="flex-1 overflow-y-auto p-10 bg-slate-50">
          <div className="max-w-7xl mx-auto">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
};

export default AdminLayout;
