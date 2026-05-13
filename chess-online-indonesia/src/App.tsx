import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { NotificationProvider } from './contexts/NotificationContext';
import ProtectedRoute from './routes/ProtectedRoute';
import AdminRoute from './routes/AdminRoute';

// Pages - to be created
import Login from './pages/Login';
import Register from './pages/Register';
import Home from './pages/Home';
import Room from './pages/Room';
import Game from './pages/Game';
import GameResult from './pages/GameResult';
import Leaderboard from './pages/Leaderboard';
import Account from './pages/Account';
import Friends from './pages/Friends';

// Admin Pages
import AdminLayout from './pages/admin/AdminLayout';
import Hardware from './pages/admin/Hardware';
import Players from './pages/admin/Players';
import ActiveMatches from './pages/admin/ActiveMatches';
import SavedMatches from './pages/admin/SavedMatches';
import Notifications from './pages/admin/Notifications';
import Settings from './pages/admin/Settings';

export default function App() {
  return (
    <Router>
      <NotificationProvider>
        <AuthProvider>
          <Routes>
            {/* Public Routes */}
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />

            {/* Protected User Routes */}
            <Route element={<ProtectedRoute />}>
              <Route path="/home" element={<Home />} />
              <Route path="/room" element={<Room />} />
              <Route path="/game/:roomId" element={<Game />} />
              <Route path="/game-result/:matchId" element={<GameResult />} />
              <Route path="/leaderboard" element={<Leaderboard />} />
              <Route path="/account" element={<Account />} />
              <Route path="/friends" element={<Friends />} />
            </Route>

            {/* Admin Routes */}
            <Route element={<AdminRoute />}>
              <Route path="/admin" element={<AdminLayout />}>
                <Route index element={<Navigate to="/admin/hardware" replace />} />
                <Route path="hardware" element={<Hardware />} />
                <Route path="players" element={<Players />} />
                <Route path="matches/active" element={<ActiveMatches />} />
                <Route path="matches/saved" element={<SavedMatches />} />
                <Route path="notifications" element={<Notifications />} />
                <Route path="settings" element={<Settings />} />
              </Route>
            </Route>

            {/* Redirects */}
            <Route path="/" element={<Navigate to="/home" replace />} />
            <Route path="*" element={<Navigate to="/home" replace />} />
          </Routes>
        </AuthProvider>
      </NotificationProvider>
    </Router>
  );
}
