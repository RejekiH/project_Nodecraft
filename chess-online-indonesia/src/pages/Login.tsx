import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useNotification } from '../contexts/NotificationContext';
import { Eye, EyeOff, LogIn } from 'lucide-react';
import { motion } from 'motion/react';
import { api } from '../services/api';

const Login: React.FC = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);

  const { login, isAuthenticated } = useAuth();
  const { showToast } = useNotification();
  const navigate = useNavigate();

  useEffect(() => {
    if (isAuthenticated) {
      navigate('/home');
    }
  }, [isAuthenticated, navigate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!username || !password) {
      showToast('Harap isi semua field', 'warning');
      return;
    }

    setLoading(true);
    try {
      const data = await api.post<{ token: string; user: any }>('/user/login', {
        username,
        password,
      });
      login(data.token, data.user);
      showToast(`Selamat datang kembali, ${data.user.name}!`, 'success');
      navigate('/home');
    } catch (err: any) {
      showToast(err.message || 'Gagal masuk. Periksa kembali username dan password.', 'error');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-chess-bg flex items-center justify-center p-4 relative overflow-hidden">
      {/* Background Pattern */}
      <div className="absolute inset-0 opacity-5 pointer-events-none grid grid-cols-8 grid-rows-8">
        {[...Array(64)].map((_, i) => (
          <div key={i} className={`aspect-square ${(Math.floor(i / 8) + i) % 2 === 0 ? 'bg-white' : 'bg-transparent'}`} />
        ))}
      </div>

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-md z-10"
      >
        <div className="bg-chess-card border border-chess-border rounded-2xl shadow-2xl p-8 backdrop-blur-sm">
          <div className="text-center mb-8">
            <span className="text-7xl text-chess-green mb-4 block">♞</span>
            <h1 className="text-3xl font-black tracking-tight">MASUK <span className="text-chess-green">CHESSID</span></h1>
            <p className="text-chess-muted mt-2">Masuk untuk mulai bermain catur online</p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label className="block text-sm font-bold mb-2 text-chess-muted">Username</label>
              <input
                type="text"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green transition-all"
                placeholder="Masukkan username Anda"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-bold mb-2 text-chess-muted">Password</label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full bg-chess-bg border border-chess-border rounded-xl px-4 py-3 outline-none focus:border-chess-green transition-all"
                  placeholder="Masukkan password"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-chess-muted hover:text-white transition-colors"
                >
                  {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-chess-green hover:brightness-110 disabled:opacity-50 disabled:cursor-not-allowed py-4 rounded-xl font-black text-xl shadow-lg shadow-chess-green/20 transition-all active:scale-95 flex items-center justify-center gap-3 mt-4"
            >
              {loading ? (
                <div className="w-6 h-6 border-4 border-white/30 border-t-white rounded-full animate-spin"></div>
              ) : (
                <>
                  <LogIn className="w-6 h-6" />
                  MASUK
                </>
              )}
            </button>
          </form>

          <div className="mt-8 pt-8 border-t border-chess-border text-center">
            <p className="text-chess-muted">
              Belum punya akun?{' '}
              <Link to="/register" className="text-chess-green font-bold hover:underline">
                Daftar Sekarang
              </Link>
            </p>
          </div>
        </div>
      </motion.div>
    </div>
  );
};

export default Login;
