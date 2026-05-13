import React, { useState, useEffect } from 'react';
import { api } from '../../services/api';
import { BellRing, AlertCircle, AlertTriangle, Info, CheckCircle2, ShieldAlert, Clock, RefreshCw } from 'lucide-react';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import { useNotification } from '../../contexts/NotificationContext';

const Notifications: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [notifications, setNotifications] = useState<any[]>([]);
  const { showToast } = useNotification();

  const fetchData = async () => {
    try {
      const data = await api.get<{ notifications: any[] }>('/admin/notifications');
      setNotifications(data.notifications);
    } catch (err) {
      console.error('Failed to fetch admin notifications', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleResolve = async (id: number) => {
    try {
      await api.put(`/admin/notifications/${id}/resolve`);
      showToast('Masalah ditandai sebagai selesai', 'success');
      fetchData();
    } catch (err) {
      showToast('Gagal mengubah status notifikasi', 'error');
    }
  };

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-8 animate-fade-in max-w-4xl">
       <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold italic tracking-tighter uppercase mb-1">Pusat Notifikasi</h1>
          <p className="text-slate-500 text-sm font-bold uppercase tracking-widest">Laporan Masalah Sistem & Keamanan</p>
        </div>
        <button 
          onClick={() => { setLoading(true); fetchData(); }}
          className="p-2.5 hover:bg-slate-50 rounded-xl border border-slate-200 bg-white shadow-sm transition-all text-slate-400 hover:text-chess-indigo"
        >
          <RefreshCw className="w-5 h-5" />
        </button>
      </div>

       <div className="space-y-4">
            {notifications.length > 0 ? notifications.map((notif) => (
                <div 
                    key={notif.id} 
                    className={`
                        p-6 bg-chess-card border rounded-3xl flex items-start gap-6 transition-all
                        ${notif.resolved ? 'border-chess-border opacity-70' : 'border-red-500/30' }
                        ${!notif.resolved && notif.severity === 'critical' ? 'bg-red-500/5 shadow-[0_10px_40px_rgba(239,68,68,0.1)]' : ''}
                    `}
                >
                    <div className={`
                        p-3 rounded-2xl flex-shrink-0
                        ${notif.severity === 'critical' ? 'bg-red-600/10 text-red-500' : 
                          notif.severity === 'warning' ? 'bg-yellow-500/10 text-yellow-500' : 
                          'bg-blue-500/10 text-blue-500'}
                    `}>
                        {notif.severity === 'critical' ? <ShieldAlert className="w-6 h-6" /> : 
                         notif.severity === 'warning' ? <AlertTriangle className="w-6 h-6" /> : 
                         <Info className="w-6 h-6" />}
                    </div>

                    <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                             <span className={`text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-lg ${
                                 notif.severity === 'critical' ? 'bg-rose-500 text-white' : 
                                 notif.severity === 'warning' ? 'bg-amber-500 text-white' : 
                                 'bg-blue-500 text-white'
                             }`}>
                                 {notif.severity}
                             </span>
                             <div className="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 uppercase">
                                 <Clock className="w-3 h-3" /> {new Date(notif.created_at).toLocaleString()}
                             </div>
                        </div>
                        <h3 className="text-xl font-bold mb-2 text-slate-800">{notif.message}</h3>
                        <p className="text-xs text-slate-400 font-bold uppercase tracking-widest mb-4">Server: <span className="text-slate-600 italic">{notif.server}</span></p>
                        
                        {!notif.resolved && (
                             <button 
                                 onClick={() => handleResolve(notif.id)}
                                 className="px-6 py-2 bg-slate-50 hover:bg-emerald-500 hover:text-white rounded-xl text-xs font-bold transition-all border border-slate-200 hover:border-emerald-500 shadow-sm"
                             >
                                 TANDAI SELESAI
                             </button>
                        )}
                        {notif.resolved && (
                             <div className="inline-flex items-center gap-2 text-emerald-600 text-xs font-bold uppercase italic">
                                 <CheckCircle2 className="w-4 h-4" /> Masalah Teratasi
                             </div>
                        )}
                    </div>
                </div>
            )) : (
                <div className="p-20 text-center text-chess-muted flex flex-col items-center gap-4 bg-white border border-dashed border-slate-200 rounded-2xl shadow-sm">
                    <BellRing className="w-12 h-12 opacity-10" />
                    <p className="text-sm font-bold uppercase tracking-widest">Tidak ada notifikasi sistem saat ini.</p>
                </div>
            )}
       </div>
    </div>
  );
};

export default Notifications;
