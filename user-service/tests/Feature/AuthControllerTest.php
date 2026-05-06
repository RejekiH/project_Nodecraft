<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

/**
 * AuthControllerTest
 * 
 * Jalankan: php artisan test --filter=AuthControllerTest
 * Atau:     php artisan test
 */
class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Hapus semua user test sebelum tiap test
        User::where('email', 'like', '%@test.nodechess%')->delete();
    }

    // ─────────────────────────────────────────────
    // REGISTER
    // ─────────────────────────────────────────────

    /** @test */
    public function register_berhasil_dengan_data_valid(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'testuser1',
            'email'                 => 'testuser1@test.nodechess',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'user' => ['id', 'username', 'email', 'rating', 'wins'],
                         'access_token',
                         'refresh_token',
                         'expires_in',
                         'token_type',
                     ],
                 ])
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.user.username', 'testuser1')
                 ->assertJsonPath('data.user.rating', 0)
                 ->assertJsonPath('data.token_type', 'Bearer');
    }

    /** @test */
    public function register_gagal_username_sudah_dipakai(): void
    {
        // Buat user pertama
        User::create([
            'username' => 'duplikat',
            'email'    => 'duplikat@test.nodechess',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'username'              => 'duplikat',
            'email'                 => 'beda@test.nodechess',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(409)
                 ->assertJsonPath('success', false)
                 ->assertJsonPath('error.code', 'CONFLICT');
    }

    /** @test */
    public function register_gagal_username_terlalu_pendek(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'ab',
            'email'                 => 'ab@test.nodechess',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /** @test */
    public function register_gagal_konfirmasi_password_tidak_cocok(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username'              => 'validuser',
            'email'                 => 'valid@test.nodechess',
            'password'              => 'password123',
            'password_confirmation' => 'passwordXXX',
        ]);

        $response->assertStatus(422);
    }

    // ─────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────

    /** @test */
    public function login_berhasil_dengan_username(): void
    {
        User::create([
            'username' => 'logintest',
            'email'    => 'logintest@test.nodechess',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'logintest',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
    }

    /** @test */
    public function login_berhasil_dengan_email(): void
    {
        User::create([
            'username' => 'emailtest',
            'email'    => 'emailtest@test.nodechess',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'emailtest@test.nodechess',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function login_gagal_password_salah(): void
    {
        User::create([
            'username' => 'wrongpass',
            'email'    => 'wrongpass@test.nodechess',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login'    => 'wrongpass',
            'password' => 'salahpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────
    // PROTECTED ROUTES
    // ─────────────────────────────────────────────

    /** @test */
    public function akses_tanpa_token_ditolak(): void
    {
        $response = $this->getJson('/api/users/me');

        $response->assertStatus(401)
                 ->assertJsonPath('error.code', 'MISSING_TOKEN');
    }

    /** @test */
    public function akses_dengan_token_valid_berhasil(): void
    {
        // Register dulu untuk mendapat token
        $registerResponse = $this->postJson('/api/auth/register', [
            'username'              => 'tokentest',
            'email'                 => 'tokentest@test.nodechess',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $token = $registerResponse->json('data.access_token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/users/me');

        $response->assertStatus(200)
                 ->assertJsonPath('data.username', 'tokentest');
    }

    // ─────────────────────────────────────────────
    // LEADERBOARD
    // ─────────────────────────────────────────────

    /** @test */
    public function leaderboard_dapat_diakses_tanpa_token(): void
    {
        $response = $this->getJson('/api/users/leaderboard');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'meta' => ['total', 'limit', 'offset']]);
    }
}
