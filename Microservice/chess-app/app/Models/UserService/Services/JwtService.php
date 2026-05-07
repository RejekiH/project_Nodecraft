<?php

namespace App\Modules\UserService\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use App\Modules\UserService\Models\User;
use App\Modules\UserService\Exceptions\AuthException;

/**
 * JwtService
 * 
 * Mengelola pembuatan dan validasi JWT token.
 * Secret JWT di-share ke semua module agar token bisa diverifikasi
 * langsung via shared middleware tanpa memanggil UserService (lihat diagram).
 * 
 * Payload JWT:
 *   sub  : user_id (MongoDB ObjectId string)
 *   usr  : username
 *   iat  : issued at
 *   exp  : expiry
 *   type : 'access' | 'refresh'
 */
class JwtService
{
    private string $secret;
    private int    $accessTtl;
    private int    $refreshTtl;
    private string $algo = 'HS256';

    public function __construct()
    {
        $this->secret     = config('jwt.secret');
        $this->accessTtl  = config('jwt.expires_in');
        $this->refreshTtl = config('jwt.refresh_expires_in');

        if (empty($this->secret)) {
            throw new \RuntimeException('JWT_SECRET belum dikonfigurasi di .env');
        }
    }

    // ─────────────────────────────────────────────
    // GENERATE TOKENS
    // ─────────────────────────────────────────────

    public function generateAccessToken(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss'  => config('app.name'),
            'sub'  => (string) $user->_id,
            'usr'  => $user->username,
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $this->accessTtl,
            'type' => 'access',
        ], $this->secret, $this->algo);
    }

    public function generateRefreshToken(User $user): string
    {
        $now = time();

        return JWT::encode([
            'iss'  => config('app.name'),
            'sub'  => (string) $user->_id,
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $this->refreshTtl,
            'type' => 'refresh',
        ], $this->secret, $this->algo);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    public function generateTokenPair(User $user): array
    {
        return [
            'access_token'  => $this->generateAccessToken($user),
            'refresh_token' => $this->generateRefreshToken($user),
            'expires_in'    => $this->accessTtl,
            'token_type'    => 'Bearer',
        ];
    }

    // ─────────────────────────────────────────────
    // VALIDATE TOKENS
    // ─────────────────────────────────────────────

    /**
     * Decode dan validasi token JWT
     * 
     * @throws AuthException
     */
    public function decode(string $token, string $expectedType = 'access'): object
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, $this->algo));

            if (($payload->type ?? '') !== $expectedType) {
                throw new AuthException(
                    "Token type tidak sesuai. Diharapkan: {$expectedType}",
                    401
                );
            }

            return $payload;

        } catch (ExpiredException) {
            throw new AuthException('Token sudah kadaluarsa', 401);

        } catch (SignatureInvalidException) {
            throw new AuthException('Token tidak valid (signature mismatch)', 401);

        } catch (AuthException $e) {
            throw $e;

        } catch (\Exception $e) {
            throw new AuthException('Token tidak dapat diproses: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Validasi access token dan return User object
     * 
     * @throws AuthException
     */
    public function validateAccessToken(string $token): User
    {
        $payload = $this->decode($token, 'access');

        $user = User::find($payload->sub);
        if (!$user) {
            throw new AuthException('User tidak ditemukan', 401);
        }

        return $user;
    }

    /**
     * Validasi refresh token dan return User object
     * 
     * @throws AuthException
     */
    public function validateRefreshToken(string $token): User
    {
        $payload = $this->decode($token, 'refresh');

        $user = User::find($payload->sub);
        if (!$user) {
            throw new AuthException('User tidak ditemukan', 401);
        }

        return $user;
    }

    /**
     * Extract Bearer token dari Authorization header
     * 
     * @throws AuthException
     */
    public function extractBearerToken(string $authHeader): string
    {
        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthException('Format Authorization header tidak valid. Gunakan: Bearer <token>', 401);
        }

        $token = trim(substr($authHeader, 7));
        if (empty($token)) {
            throw new AuthException('Token kosong', 401);
        }

        return $token;
    }
}
