import React from 'react';
import { NavLink } from 'react-router-dom';
import { Home, Play, Trophy, Users, User, ShieldAlert } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';

const Sidebar: React.FC<{ isOpen: boolean, onClose: () => void }> = ({ isOpen, onClose }) => {
  const { isAdmin } = useAuth();

  const links = [
    { to: '/home', icon: Home, label: 'Beranda' },
    { to: '/room', icon: Play, label: 'Main Sekarang' },
    { to: '/leaderboard', icon: Trophy, label: 'Leaderboard' },
    { to: '/friends', icon: Users, label: 'Teman' },
    { to: '/account', icon: User, label: 'Akun' },
  ];

  const sidebarClasses = `
    fixed inset-y-0 left-0 z-40 w-64 bg-chess-sidebar border-r border-chess-border transform 
    lg:translate-x-0 lg:static transition-transform duration-300 ease-in-out
    ${isOpen ? 'translate-x-0' : '-translate-x-full'}
  `;

  return (
    <>
      {/* Backdrop for mobile */}
      {isOpen && (
        <div 
          className="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm lg:hidden"
          onClick={onClose}
        />
      )}

      <aside className={sidebarClasses}>
        <div className="flex flex-col h-full py-6">
          <div className="flex-1 space-y-1 px-3">
            {links.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                onClick={() => onClose()}
                className={({ isActive }) => `
                  flex items-center gap-4 px-4 py-3 rounded-xl transition-all duration-200 group
                  ${isActive 
                    ? 'bg-chess-green text-white shadow-lg shadow-chess-green/20 font-bold' 
                    : 'text-chess-muted hover:text-white hover:bg-white/5'}
                `}
              >
                <link.icon className={`w-5 h-5 transition-transform group-hover:scale-110`} />
                <span>{link.label}</span>
              </NavLink>
            ))}
          </div>

          {isAdmin && (
            <div className="px-3 pt-6 border-t border-chess-border/50">
              <NavLink
                to="/admin"
                onClick={() => onClose()}
                className={({ isActive }) => `
                  flex items-center gap-4 px-4 py-3 rounded-xl transition-all duration-200 border border-transparent
                  ${isActive 
                    ? 'bg-red-600 text-white shadow-lg shadow-red-600/20 font-bold' 
                    : 'text-red-400 hover:text-red-300 hover:bg-red-500/10 hover:border-red-500/20'}
                `}
              >
                <ShieldAlert className="w-5 h-5" />
                <span>Panel Admin</span>
              </NavLink>
            </div>
          )}
        </div>
      </aside>
    </>
  );
};

export default Sidebar;
