<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use App\Models\User;
use App\Exceptions\AuthException;

/**
 * JwtService
 * 
 * Mengelola pembuatan dan validasi JWT token.
 * Token digunakan oleh semua service lain untuk autentikasi.
 * 
 * Payload JWT:
 * - sub  : user_id (MongoDB ObjectId string)
 * - usr  : username
 * - iat  : issued at
 * - exp  : expiry
 * - type : 'access' | 'refresh'
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

    /**
     * Buat access token (short-lived)
     */
    public function generateAccessToken(User $user): string
    {
        $now = time();

        $payload = [
            'iss'  => config('app.name'),
            'sub'  => (string) $user->_id,
            'usr'  => $user->username,
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $this->accessTtl,
            'type' => 'access',
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Buat refresh token (long-lived)
     */
    public function generateRefreshToken(User $user): string
    {
        $now = time();

        $payload = [
            'iss'  => config('app.name'),
            'sub'  => (string) $user->_id,
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $this->refreshTtl,
            'type' => 'refresh',
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Buat pasangan access + refresh token
     * 
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
     * Decode dan validasi token
     * 
     * @param string $token JWT token
     * @param string $expectedType 'access' | 'refresh'
     * @throws AuthException jika token tidak valid
     * @return object payload JWT
     */
    public function decode(string $token, string $expectedType = 'access'): object
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, $this->algo));

            // Validasi type
            if (($payload->type ?? '') !== $expectedType) {
                throw new AuthException(
                    "Token type tidak sesuai. Diharapkan: {$expectedType}",
                    401
                );
            }

            return $payload;

        } catch (ExpiredException $e) {
            throw new AuthException('Token sudah kadaluarsa', 401);

        } catch (SignatureInvalidException $e) {
            throw new AuthException('Token tidak valid (signature mismatch)', 401);

        } catch (AuthException $e) {
            throw $e;

        } catch (\Exception $e) {
            throw new AuthException('Token tidak dapat diproses: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Validasi access token dan return User
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
     * Validasi refresh token dan return User
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
     * Extract Bearer token dari header Authorization
     * 
     * @param string $authHeader "Bearer eyJhbG..."
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
