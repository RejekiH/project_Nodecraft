import React from 'react';
import { NavLink } from 'react-router-dom';
import { 
  Cpu, 
  Users, 
  Gamepad2, 
  Save, 
  BellRing, 
  Settings, 
  ArrowLeft,
  LayoutDashboard
} from 'lucide-react';

const AdminSidebar: React.FC = () => {
  const links = [
    { to: '/admin/hardware', icon: Cpu, label: 'Hardware' },
    { to: '/admin/players', icon: Users, label: 'Pemain' },
    { to: '/admin/matches/active', icon: Gamepad2, label: 'Match Live', badge: 3 },
    { to: '/admin/matches/saved', icon: Save, label: 'Arsip' },
    { to: '/admin/notifications', icon: BellRing, label: 'Notifikasi', badge: 5 },
    { to: '/admin/settings', icon: Settings, label: 'Pengaturan' },
  ];

  return (
    <div className="w-64 bg-chess-sidebar border-r border-chess-border flex flex-col h-full">
      <div className="p-6 flex items-center gap-3">
        <div className="bg-chess-indigo p-2 rounded-xl">
          <LayoutDashboard className="w-6 h-6 text-white" />
        </div>
        <div>
          <h2 className="font-black tracking-widest text-xs uppercase text-chess-indigo">ADMIN</h2>
          <p className="text-sm font-bold text-chess-text">CHESSID PRO</p>
        </div>
      </div>

      <nav className="flex-1 px-4 py-6 space-y-1">
        {links.map((link) => (
          <NavLink
            key={link.to}
            to={link.to}
            className={({ isActive }) => `
              flex items-center justify-between px-4 py-3 rounded-xl transition-all group
              ${isActive 
                ? 'bg-indigo-50 text-chess-indigo font-bold shadow-sm' 
                : 'text-chess-muted hover:text-chess-text hover:bg-slate-50'}
            `}
          >
            <div className="flex items-center gap-4">
              <link.icon className={`w-5 h-5 transition-transform group-hover:scale-110`} />
              <span className="text-sm">{link.label}</span>
            </div>
            {link.badge && (
              <span className={`text-[10px] px-2 py-0.5 rounded-full font-black ${link.to.includes('notifications') ? 'bg-red-500 text-white shadow-sm' : 'bg-slate-100 text-chess-muted'}`}>
                {link.badge}
              </span>
            )}
          </NavLink>
        ))}
      </nav>

      <div className="p-6 border-t border-slate-100">
        <NavLink 
          to="/home" 
          className="flex items-center gap-4 px-4 py-3 text-sm text-chess-muted hover:text-chess-text hover:bg-slate-50 rounded-xl transition-all border border-transparent hover:border-slate-200"
        >
          <ArrowLeft className="w-5 h-5" />
          <span>Kembali Player</span>
        </NavLink>
      </div>
    </div>
  );
};

export default AdminSidebar;
