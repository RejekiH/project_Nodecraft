import React, { useState, useEffect } from 'react';
import { api } from '../../services/api';
import { Settings as SettingsIcon, Save, Lock, History, Shield, LayoutDashboard } from 'lucide-react';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import { useNotification } from '../../contexts/NotificationContext';
import { useAuth } from '../../contexts/AuthContext';

const Settings: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [admin, setAdmin] = useState<any>(null);
  const [saving, setSaving] = useState(false);
  const { showToast } = useNotification();
  const { user } = useAuth();

  const [formData, setFormData] = useState({
    name: '',
    email: '',
  });

  const [pwData, setPwData] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const fetchData = async () => {
    try {
      const data = await api.get<{ admin: any }>('/admin/account');
      setAdmin(data.admin);
      setFormData({ name: data.admin.name, email: data.admin.email });
    } catch (err) {
      console.error('Failed to fetch admin data', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const handleUpdateAccount = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      await api.put('/admin/account', formData);
      showToast('Profil admin diperbarui!', 'success');
      fetchData();
    } catch (err: any) {
      showToast(err.message || 'Gagal memperbarui profil', 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleUpdatePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (pwData.password !== pwData.password_confirmation) {
      showToast('Konfirmasi password tidak cocok', 'error');
      return;
    }
    try {
      await api.put('/admin/account', { ...formData, ...pwData });
      showToast('Password admin diperbarui!', 'success');
      setPwData({ current_password: '', password: '', password_confirmation: '' });
    } catch (err: any) {
      showToast(err.message || 'Gagal memperbarui password', 'error');
    }
  };

  if (loading) return <LoadingSpinner />;

  return (
    <div className="space-y-8 animate-fade-in max-w-4xl">
       <div>
          <h1 className="text-3xl font-bold italic tracking-tighter uppercase mb-1 text-slate-900">Pengaturan Admin</h1>
          <p className="text-slate-500 text-sm font-bold uppercase tracking-widest">Kelola Profil dan Keamanan Server Utama</p>
       </div>

       <div className="grid lg:grid-cols-2 gap-8">
            {/* Account Settings */}
            <div className="bg-white border border-slate-100 rounded-2xl p-8 space-y-6 shadow-sm">
                <h3 className="text-xl font-bold flex items-center gap-2 text-slate-800">
                    <Shield className="w-5 h-5 text-chess-indigo" /> Profil Super Admin
                </h3>
                <form onSubmit={handleUpdateAccount} className="space-y-4">
                    <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase mb-2">Nama Administrator</label>
                        <input 
                            type="text" 
                            value={formData.name}
                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-chess-indigo/20 transition-all font-bold text-slate-800"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase mb-2">Alamat Email Sistem</label>
                        <input 
                            type="email" 
                            value={formData.email}
                            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-chess-indigo/20 transition-all font-bold text-slate-800"
                        />
                    </div>
                    <div className="pt-4 border-t border-slate-50">
                        <p className="text-[10px] text-slate-400 italic mb-4">Login Terakhir: {admin?.last_login}</p>
                        <button 
                            type="submit"
                            disabled={saving}
                            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white disabled:opacity-50 py-4 rounded-xl font-bold text-lg shadow-xl shadow-indigo-600/20 transition-all flex items-center justify-center gap-2"
                        >
                            {saving ? <div className="w-5 h-5 border-2 border-white/20 border-t-white rounded-full animate-spin" /> : <Save className="w-5 h-5" />}
                            SIMPAN PROFIL
                        </button>
                    </div>
                </form>
            </div>

            {/* Password Settings */}
            <div className="bg-white border border-slate-100 rounded-2xl p-8 space-y-6 shadow-sm">
                <h3 className="text-xl font-bold flex items-center gap-2 text-slate-800">
                    <Lock className="w-5 h-5 text-chess-indigo" /> Keamanan Akses
                </h3>
                <form onSubmit={handleUpdatePassword} className="space-y-4">
                    <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase mb-2">Password Saat Ini</label>
                        <input 
                            type="password" 
                            required
                            value={pwData.current_password}
                            onChange={(e) => setPwData({ ...pwData, current_password: e.target.value })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-chess-indigo/20 transition-all text-slate-800"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase mb-2">Password Baru</label>
                        <input 
                            type="password" 
                            required
                            value={pwData.password}
                            onChange={(e) => setPwData({ ...pwData, password: e.target.value })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-chess-indigo/20 transition-all text-slate-800"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase mb-2">Ulangi Password Baru</label>
                        <input 
                            type="password" 
                            required
                            value={pwData.password_confirmation}
                            onChange={(e) => setPwData({ ...pwData, password_confirmation: e.target.value })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-chess-indigo/20 transition-all text-slate-800"
                        />
                    </div>
                    <button 
                        type="submit"
                        className="w-full bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 py-4 rounded-xl font-bold text-lg transition-all flex items-center justify-center gap-2 mt-4 shadow-sm"
                    >
                        UPDATE PASSWORD
                    </button>
                </form>
            </div>
       </div>

       {/* Activity Log */}
       <div className="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
            <div className="p-6 border-b border-slate-50 flex items-center gap-2">
                <History className="w-6 h-6 text-chess-indigo" />
                <h3 className="text-sm font-bold uppercase tracking-widest text-slate-800">Log Aktivitas Administrator (30 Hari)</h3>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-left">
                    <thead>
                        <tr className="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-50 bg-slate-50/50">
                            <th className="px-8 py-4">Aksi / Event</th>
                            <th className="px-8 py-4">Waktu</th>
                            <th className="px-8 py-4 text-right">IP Address</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-50 italic text-xs text-slate-400 font-medium">
                        {[...Array(5)].map((_, i) => (
                            <tr key={i} className="hover:bg-slate-50/50 transition-colors">
                                <td className="px-8 py-5 font-bold text-slate-700 not-italic">Login Berhasil ke Dashboard</td>
                                <td className="px-8 py-4">2024-05-13 14:23:12</td>
                                <td className="px-8 py-4 text-right">192.168.1.{i * 12}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
       </div>
    </div>
  );
};

export default Settings;
