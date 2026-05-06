<?php

namespace App\Services;

use App\Models\User;
use App\Exceptions\AuthException;
use App\Exceptions\UserException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * UserService
 * 
 * Core business logic untuk manajemen user.
 * Controller hanya menangani HTTP, semua logic ada di sini.
 */
class UserService
{
    public function __construct(private JwtService $jwtService)
    {
    }

    // ─────────────────────────────────────────────
    // REGISTRASI
    // ─────────────────────────────────────────────

    /**
     * Daftarkan user baru
     * 
     * @param array $data {username, email, password}
     * @return array{user: User, tokens: array}
     * @throws UserException jika username/email sudah dipakai
     */
    public function register(array $data): array
    {
        // Cek duplikat username (case-insensitive)
        if (User::where('username', strtolower($data['username']))->exists()) {
            throw new UserException('Username sudah digunakan', 409);
        }

        // Cek duplikat email
        if (User::where('email', strtolower($data['email']))->exists()) {
            throw new UserException('Email sudah terdaftar', 409);
        }

        // Buat user
        $user = User::create([
            'username' => strtolower(trim($data['username'])),
            'email'    => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
        ]);

        Log::info('User baru terdaftar', [
            'user_id'  => (string) $user->_id,
            'username' => $user->username,
        ]);

        $tokens = $this->jwtService->generateTokenPair($user);

        return ['user' => $user, 'tokens' => $tokens];
    }

    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────

    /**
     * Login dengan username atau email + password
     * 
     * @param array $data {login, password}  -- login bisa username atau email
     * @return array{user: User, tokens: array}
     * @throws AuthException jika kredensial salah
     */
    public function login(array $data): array
    {
        $login = strtolower(trim($data['login']));

        // Cari user berdasarkan username atau email
        $user = User::where('username', $login)
                    ->orWhere('email', $login)
                    ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            // Pesan generik untuk keamanan (jangan ungkap mana yang salah)
            throw new AuthException('Username/email atau password salah', 401);
        }

        // Update status
        $user->setStatus('online');

        Log::info('User login', ['user_id' => (string) $user->_id, 'username' => $user->username]);

        $tokens = $this->jwtService->generateTokenPair($user);

        return ['user' => $user, 'tokens' => $tokens];
    }

    // ─────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────

    /**
     * Logout user - set status offline
     * (JWT stateless, tidak ada invalidasi token di sisi server untuk Fase 2)
     */
    public function logout(User $user): void
    {
        $user->setStatus('offline');

        Log::info('User logout', ['user_id' => (string) $user->_id]);
    }

    // ─────────────────────────────────────────────
    // REFRESH TOKEN
    // ─────────────────────────────────────────────

    /**
     * Tukar refresh token dengan access token baru
     * 
     * @param string $refreshToken
     * @return array tokens baru
     * @throws AuthException
     */
    public function refreshTokens(string $refreshToken): array
    {
        $user = $this->jwtService->validateRefreshToken($refreshToken);
        return $this->jwtService->generateTokenPair($user);
    }

    // ─────────────────────────────────────────────
    // PROFIL
    // ─────────────────────────────────────────────

    /**
     * Ambil profil user berdasarkan ID
     */
    public function getProfile(string $userId): User
    {
        $user = User::find($userId);
        if (!$user) {
            throw new UserException('User tidak ditemukan', 404);
        }
        return $user;
    }

    /**
     * Ambil profil user berdasarkan username
     */
    public function getProfileByUsername(string $username): User
    {
        $user = User::where('username', strtolower($username))->first();
        if (!$user) {
            throw new UserException('User tidak ditemukan', 404);
        }
        return $user;
    }

    /**
     * Update profil user (hanya field yang diizinkan)
     * 
     * @param User  $user
     * @param array $data {email?, current_password?, new_password?}
     * @throws UserException
     * @throws AuthException
     */
    public function updateProfile(User $user, array $data): User
    {
        $updates = [];

        // Update email
        if (!empty($data['email'])) {
            $newEmail = strtolower(trim($data['email']));
            if ($newEmail !== $user->email) {
                if (User::where('email', $newEmail)->where('_id', '!=', $user->_id)->exists()) {
                    throw new UserException('Email sudah digunakan oleh akun lain', 409);
                }
                $updates['email'] = $newEmail;
            }
        }

        // Update password
        if (!empty($data['new_password'])) {
            if (empty($data['current_password'])) {
                throw new AuthException('Password saat ini diperlukan untuk mengubah password', 400);
            }
            if (!Hash::check($data['current_password'], $user->password)) {
                throw new AuthException('Password saat ini salah', 401);
            }
            $updates['password'] = Hash::make($data['new_password']);
        }

        if (!empty($updates)) {
            $user->update($updates);
            Log::info('User update profil', ['user_id' => (string) $user->_id, 'fields' => array_keys($updates)]);
        }

        return $user->fresh();
    }

    // ─────────────────────────────────────────────
    // LEADERBOARD
    // ─────────────────────────────────────────────

    /**
     * Ambil leaderboard
     * 
     * @param int $limit  Jumlah user (max 100)
     * @param int $offset Untuk pagination
     */
    public function getLeaderboard(int $limit = 20, int $offset = 0): array
    {
        $limit = min($limit, 100);

        $users = User::orderBy('rating', 'desc')
                     ->skip($offset)
                     ->limit($limit)
                     ->get();

        $total = User::count();

        return [
            'data'   => $users->map(fn($u) => $u->toPublicArray())->values()->toArray(),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    // ─────────────────────────────────────────────
    // INTERNAL (dipanggil oleh service lain)
    // ─────────────────────────────────────────────

    /**
     * Update match result dari Gameplay/Room Service
     * Dipanggil via internal API dengan API key
     * 
     * @param string $userId
     * @param string $result  'win' | 'loss' | 'draw'
     * @param array  $preview Data preview match terakhir
     */
    public function applyMatchResult(string $userId, string $result, array $preview = []): User
    {
        $user = User::find($userId);
        if (!$user) {
            throw new UserException("User {$userId} tidak ditemukan", 404);
        }

        $user->applyMatchResult($result);

        if (!empty($preview)) {
            $user->updateLastMatchPreview($preview);
        }

        Log::info('Match result applied', [
            'user_id' => $userId,
            'result'  => $result,
            'new_rating' => $user->fresh()->rating,
        ]);

        return $user->fresh();
    }

    /**
     * Batch lookup users by IDs (untuk Room Service)
     */
    public function getUsersByIds(array $ids): array
    {
        return User::whereIn('_id', $ids)
                   ->get()
                   ->keyBy(fn($u) => (string) $u->_id)
                   ->map(fn($u) => $u->toPublicArray())
                   ->toArray();
    }

    /**
     * Verifikasi token JWT (untuk inter-service auth check)
     * Room/Gameplay Service bisa hit endpoint ini untuk validasi token user
     */
    public function verifyToken(string $token): array
    {
        $user = $this->jwtService->validateAccessToken($token);
        return [
            'valid'    => true,
            'user_id'  => (string) $user->_id,
            'username' => $user->username,
        ];
    }
}
