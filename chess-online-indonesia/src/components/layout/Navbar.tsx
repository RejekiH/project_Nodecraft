import React, { useState } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { LogOut, User, Award, Menu, X } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';

const Navbar: React.FC<{ sidebarOpen: boolean, setSidebarOpen: (val: boolean) => void }> = ({ sidebarOpen, setSidebarOpen }) => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [profileOpen, setProfileOpen] = useState(false);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <nav className="h-16 px-4 flex items-center justify-between border-b border-chess-border bg-chess-bg sticky top-0 z-50">
      <div className="flex items-center gap-4">
        <button 
          onClick={() => setSidebarOpen(!sidebarOpen)}
          className="lg:hidden p-2 text-chess-muted hover:text-white transition-colors"
        >
          {sidebarOpen ? <X /> : <Menu />}
        </button>
        
        <Link to="/home" className="flex items-center gap-2 group">
          <span className="text-3xl text-chess-green group-hover:scale-110 transition-transform">♞</span>
          <span className="text-xl font-black tracking-tight hidden sm:block">CHESS<span className="text-chess-green">ID</span></span>
        </Link>
      </div>

      <div className="flex items-center gap-3 sm:gap-6">
        {user && (
          <div className="flex items-center gap-2 px-3 py-1 bg-white/5 rounded-full border border-chess-border">
            <Award className="w-4 h-4 text-chess-green" />
            <span className="text-sm font-bold">{user.points}</span>
          </div>
        )}

        <div className="relative">
          <button 
            onClick={() => setProfileOpen(!profileOpen)}
            className="flex items-center gap-3 hover:bg-white/5 p-1.5 rounded-lg transition-all border border-transparent hover:border-chess-border"
          >
            <div className="w-8 h-8 rounded-full bg-chess-green flex items-center justify-center font-bold text-lg select-none">
              {(user?.name || '?').charAt(0).toUpperCase()}
            </div>
            <div className="hidden sm:block text-left">
              <p className="text-xs font-bold leading-none mb-0.5">{user?.name}</p>
              <p className="text-[10px] text-chess-muted leading-none">@{user?.username}</p>
            </div>
          </button>

          {profileOpen && (
            <>
              <div 
                className="fixed inset-0 z-10" 
                onClick={() => setProfileOpen(false)}
              />
              <div className="absolute right-0 mt-2 w-48 bg-chess-card border border-chess-border rounded-xl shadow-2xl z-20 overflow-hidden py-1">
                <Link 
                  to="/account" 
                  className="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-white/5 transition-colors"
                  onClick={() => setProfileOpen(false)}
                >
                  <User className="w-4 h-4" />
                  Profil Saya
                </Link>
                <button 
                  onClick={() => { handleLogout(); setProfileOpen(false); }}
                  className="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-red-500/10 text-red-400 transition-colors"
                >
                  <LogOut className="w-4 h-4" />
                  Keluar
                </button>
              </div>
            </>
          )}
        </div>
      </div>
    </nav>
  );
};

export default Navbar;
