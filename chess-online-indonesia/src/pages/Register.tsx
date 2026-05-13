import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useNotification } from '../contexts/NotificationContext';
import { Eye, EyeOff, UserPlus } from 'lucide-react';
import { motion } from 'motion/react';
import { api } from '../services/api';

const Register: React.FC = () => {
  const [formData, setFormData] = useState({
    name: '',
    username: '',
    password: '',
    password_confirmation: '',
  });
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [strength, setStrength] = useState<'Weak' | 'Medium' | 'Strong'>('Weak');

  const { isAuthenticated } = useAuth();
  const { showToast } = useNotification();
  const navigate = useNavigate();

  useEffect(() => {
    if (isAuthenticated) navigate('/home');
  }, [isAuthenticated, navigate]);

  useEffect(() => {
    const pw = formData.password;
    if (pw.length > 10 && /[A-Z]/.test(pw) && /[0-9]/.test(pw)) setStrength('Strong');
    else if (pw.length >= 6) setStrength('Medium');
    else setStrength('Weak');
  }, [formData.password]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (formData.password !== formData.password_confirmation) {
      showToast('Konfirmasi password tidak cocok', 'error');
      return;
    }

    setLoading(true);
    try {
      await api.post('/user/register', formData);
      showToast('Pendaftaran berhasil! Silakan masuk.', 'success');
      navigate('/login');
    } catch (err: any) {
      showToast(err.message || 'Pendaftaran gagal.', 'error');
    } finally {
      setLoading(false);
    }
  };

  const isFormValid = formData.name && formData.username.length >= 3 && formData.password.length >= 6 && formData.password === formData.password_confirmation;

  return (
    <div className="min-h-screen bg-chess-bg flex items-center justify-center p-4 relative overflow-hidden">
      <div className="absolute inset-0 opacity-5 pointer-events-none grid grid-cols-8 grid-rows-8">
        {[...Array(64)].map((_, i) => (
          <div key={i} className={`aspect-square ${(Math.floor(i / 8) + i) % 2 === 0 ? 'bg-white' : 'bg-transparent'}`} />
        ))}
      </div>

      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="w-full max-w-md z-10 my-8">
        <div className="bg-chess-card border border-chess-border rounded-2xl shadow-2xl p-8 backdrop-blur-sm">
          <div className="text-center mb-8">
            <span className="text-6xl text-chess-green mb-2 block">♞</span>
            <h1 className="text-3xl font-black tracking-tight">DAFTAR <span className="text-chess-green">CHESSID</span></h1>
            <p className="text-chess-muted mt-2">Gabung dengan komunitas catur terbesar</p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-bold mb-1 text-chess-muted">Nama Lengkap</label>
              <input
                type="text"
                required
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green transition-all"
                placeholder="Nama Anda"
              />
            </div>

            <div>
              <label className="block text-sm font-bold mb-1 text-chess-muted">Username</label>
              <input
                type="text"
                required
                value={formData.username}
                onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green transition-all"
                placeholder="Username (min 3 karakter)"
              />
            </div>

            <div>
              <label className="block text-sm font-bold mb-1 text-chess-muted">Password</label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  required
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green transition-all"
                  placeholder="Password"
                />
                <button type="button" onClick={() => setShowPassword(!showPassword)} className="absolute right-3 top-1/2 -translate-y-1/2 text-chess-muted hover:text-white">
                  {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                </button>
              </div>
              {formData.password && (
                <div className="mt-2 flex items-center gap-2">
                  <div className="flex-1 h-1.5 bg-white/5 rounded-full overflow-hidden">
                    <div 
                      className={`h-full transition-all duration-500 ${
                        strength === 'Weak' ? 'w-1/3 bg-red-500' : 
                        strength === 'Medium' ? 'w-2/3 bg-yellow-500' : 'w-full bg-green-500'
                      }`} 
                    />
                  </div>
                  <span className={`text-[10px] font-bold ${
                    strength === 'Weak' ? 'text-red-500' : 
                    strength === 'Medium' ? 'text-yellow-500' : 'text-green-500'
                  }`}>
                    {strength}
                  </span>
                </div>
              )}
            </div>

            <div>
              <label className="block text-sm font-bold mb-1 text-chess-muted">Konfirmasi Password</label>
              <input
                type="password"
                required
                value={formData.password_confirmation}
                onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green transition-all"
                placeholder="Ulangi password"
              />
            </div>

            <button
              type="submit"
              disabled={loading || !isFormValid}
              className="w-full bg-chess-green hover:brightness-110 disabled:opacity-30 disabled:cursor-not-allowed py-4 rounded-xl font-black text-xl shadow-lg shadow-chess-green/20 transition-all flex items-center justify-center gap-3 mt-4"
            >
              {loading ? (
                <div className="w-6 h-6 border-4 border-white/30 border-t-white rounded-full animate-spin"></div>
              ) : (
                <>
                  <UserPlus className="w-6 h-6" />
                  DAFTAR SEKARANG
                </>
              )}
            </button>
          </form>

          <div className="mt-8 pt-8 border-t border-chess-border text-center">
            <p className="text-chess-muted">
              Sudah punya akun?{' '}
              <Link to="/login" className="text-chess-green font-bold hover:underline">
                Masuk
              </Link>
            </p>
          </div>
        </div>
      </motion.div>
    </div>
  );
};

export default Register;
