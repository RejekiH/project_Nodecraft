<?php

namespace App\Modules\UserService\Services;

use App\Modules\UserService\Models\User;
use App\Modules\UserService\Exceptions\AuthException;
use App\Modules\UserService\Exceptions\UserException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * UserService
 * 
 * Core business logic untuk manajemen user.
 * Controller hanya menangani HTTP — semua logika ada di sini.
 * 
 * Tanggung jawab modul ini (sesuai deskripsi proyek):
 *  - Registrasi akun baru
 *  - Login & autentikasi JWT
 *  - Profil pengguna (baca & update)
 *  - Leaderboard
 *  - Endpoint internal untuk update hasil match (dipanggil RoomService/BackupService)
 *  - Verifikasi token untuk inter-service auth
 */
class UserService
{
    public function __construct(private JwtService $jwtService) {}

    // ─────────────────────────────────────────────
    // REGISTRASI
    // ─────────────────────────────────────────────

    /**
     * @param array $data {username, email, password}
     * @return array{user: User, tokens: array}
     * @throws UserException
     */
    public function register(array $data): array
    {
        if (User::where('username', strtolower($data['username']))->exists()) {
            throw new UserException('Username sudah digunakan', 409);
        }

        if (User::where('email', strtolower($data['email']))->exists()) {
            throw new UserException('Email sudah terdaftar', 409);
        }

        $user = User::create([
            'username' => strtolower(trim($data['username'])),
            'email'    => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
        ]);

        Log::info('User baru terdaftar', [
            'user_id'  => (string) $user->_id,
            'username' => $user->username,
        ]);

        return ['user' => $user, 'tokens' => $this->jwtService->generateTokenPair($user)];
    }

    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────

    /**
     * @param array $data {login, password}  — login bisa username atau email
     * @return array{user: User, tokens: array}
     * @throws AuthException
     */
    public function login(array $data): array
    {
        $login = strtolower(trim($data['login']));

        $user = User::where('username', $login)
                    ->orWhere('email', $login)
                    ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            // Pesan generik untuk keamanan — jangan ungkap mana yang salah
            throw new AuthException('Username/email atau password salah', 401);
        }

        $user->setStatus('online');

        Log::info('User login', ['user_id' => (string) $user->_id, 'username' => $user->username]);

        return ['user' => $user, 'tokens' => $this->jwtService->generateTokenPair($user)];
    }

    // ─────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────

    /**
     * JWT stateless — logout cukup set status offline.
     * Token blacklisting tidak diperlukan untuk Fase ini.
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

    public function getProfile(string $userId): User
    {
        $user = User::find($userId);
        if (!$user) {
            throw new UserException('User tidak ditemukan', 404);
        }
        return $user;
    }

    public function getProfileByUsername(string $username): User
    {
        $user = User::where('username', strtolower($username))->first();
        if (!$user) {
            throw new UserException('User tidak ditemukan', 404);
        }
        return $user;
    }

    /**
     * Update profil: hanya email dan password yang bisa diubah.
     * 
     * @param array $data {email?, current_password?, new_password?}
     * @throws UserException|AuthException
     */
    public function updateProfile(User $user, array $data): User
    {
        $updates = [];

        if (!empty($data['email'])) {
            $newEmail = strtolower(trim($data['email']));
            if ($newEmail !== $user->email) {
                if (User::where('email', $newEmail)->where('_id', '!=', $user->_id)->exists()) {
                    throw new UserException('Email sudah digunakan oleh akun lain', 409);
                }
                $updates['email'] = $newEmail;
            }
        }

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
            Log::info('User update profil', [
                'user_id' => (string) $user->_id,
                'fields'  => array_keys($updates),
            ]);
        }

        return $user->fresh();
    }

    // ─────────────────────────────────────────────
    // LEADERBOARD
    // ─────────────────────────────────────────────

    /**
     * @param int $limit  max 100
     * @param int $offset untuk pagination
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
    // INTERNAL — dipanggil oleh module lain
    // ─────────────────────────────────────────────

    /**
     * Update hasil match dari RoomService atau BackupService.
     * Dipanggil via internal API dengan X-Internal-Key.
     * 
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
            'user_id'    => $userId,
            'result'     => $result,
            'new_rating' => $user->fresh()->rating,
        ]);

        return $user->fresh();
    }

    /**
     * Batch lookup users by IDs — digunakan RoomService untuk info pemain
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
     * Verifikasi JWT token — untuk inter-service auth check.
     * RoomService / GameplayService bisa memanggil endpoint ini
     * sebagai alternatif validasi via shared middleware.
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
