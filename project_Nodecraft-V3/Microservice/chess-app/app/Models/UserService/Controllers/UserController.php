<?php

namespace App\Modules\UserService\Controllers;

use App\Modules\UserService\Services\UserService;
use App\Modules\UserService\Requests\UpdateProfileRequest;
use App\Modules\UserService\Requests\ApplyMatchResultRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * UserController
 * 
 * Endpoint publik (butuh JWT):
 *   GET  /api/user/profile            - Profil sendiri
 *   PUT  /api/user/profile            - Update profil sendiri
 *   GET  /api/user/{username}         - Profil publik user lain
 *   GET  /api/user/leaderboard        - Leaderboard
 * 
 * Endpoint internal (butuh X-Internal-Key):
 *   POST /api/internal/user/{id}/match-result - Update rating setelah match
 *   POST /api/internal/user/batch             - Batch lookup users
 * 
 * Prefix /api/user sesuai spesifikasi deskripsi proyek.
 */
class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    // ─────────────────────────────────────────────
    // GET /api/user/profile
    // ─────────────────────────────────────────────

    /**
     * Profil user yang sedang login
     * 
     * Header: Authorization: Bearer <token>
     * 
     * Response 200:
     * {
     *   "success": true,
     *   "data": {
     *     "id": "...", "username": "alice", "email": "alice@example.com",
     *     "rating": 150, "wins": 10, "losses": 3, "draws": 1,
     *     "total_games": 14, "win_rate": 71.4,
     *     "status": "online", "last_match_preview": null,
     *     "created_at": "...", "updated_at": "..."
     *   }
     * }
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user->toPrivateArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // PUT /api/user/profile
    // ─────────────────────────────────────────────

    /**
     * Update profil (email atau password)
     * 
     * Header: Authorization: Bearer <token>
     * Body: { email?, current_password?, new_password?, new_password_confirmation? }
     * 
     * Response 200:
     * { "success": true, "message": "Profil berhasil diperbarui", "data": {...user} }
     */
    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data'    => $user->toPrivateArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/user/{username}
    // ─────────────────────────────────────────────

    /**
     * Profil publik user berdasarkan username
     * Tidak butuh autentikasi.
     * 
     * Response 200:
     * { "success": true, "data": { id, username, rating, wins, losses, ... } }
     */
    public function show(string $username): JsonResponse
    {
        $user = $this->userService->getProfileByUsername($username);

        return response()->json([
            'success' => true,
            'data'    => $user->toPublicArray(),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /api/user/leaderboard
    // ─────────────────────────────────────────────

    /**
     * Leaderboard pemain dengan rating tertinggi
     * 
     * Query params: limit (default 20, max 100), offset (default 0)
     * 
     * Response 200:
     * {
     *   "success": true,
     *   "data": [ { username, rating, wins, ..., last_match_preview } ],
     *   "meta": { "total": 500, "limit": 20, "offset": 0 }
     * }
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $limit  = (int) $request->query('limit', 20);
        $offset = (int) $request->query('offset', 0);

        $result = $this->userService->getLeaderboard($limit, $offset);

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'meta'    => [
                'total'  => $result['total'],
                'limit'  => $result['limit'],
                'offset' => $result['offset'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // INTERNAL: POST /api/internal/user/{id}/match-result
    // ─────────────────────────────────────────────

    /**
     * Update rating user setelah match selesai.
     * Dipanggil oleh RoomService atau BackupService.
     * 
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     * Body: { result: "win"|"loss"|"draw", preview: {...} }
     * 
     * Response 200:
     * {
     *   "success": true,
     *   "data": { "user_id": "...", "result": "win", "new_rating": 165 }
     * }
     */
    public function applyMatchResult(ApplyMatchResultRequest $request, string $userId): JsonResponse
    {
        $user = $this->userService->applyMatchResult(
            $userId,
            $request->result,
            $request->preview ?? []
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'user_id'    => $userId,
                'result'     => $request->result,
                'new_rating' => $user->rating,
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // INTERNAL: POST /api/internal/user/batch
    // ─────────────────────────────────────────────

    /**
     * Batch lookup users berdasarkan daftar IDs.
     * Digunakan RoomService untuk mendapatkan info pemain dalam satu request.
     * 
     * Header: X-Internal-Key: <INTERNAL_API_KEY>
     * Body: { ids: ["id1", "id2"] }
     * 
     * Response 200:
     * { "success": true, "data": { "id1": {...user}, "id2": {...user} } }
     */
    public function batchLookup(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array|max:50',
            'ids.*' => 'required|string',
        ]);

        $users = $this->userService->getUsersByIds($request->ids);

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }
}
