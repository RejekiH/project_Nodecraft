<?php

namespace App\Modules\GameplayService\Exceptions;

use RuntimeException;

/**
 * GameplayException
 *
 * Exception untuk semua error domain GameplayService.
 * Setiap error memiliki kode unik, pesan deskriptif, dan HTTP status.
 *
 * Kode error:
 *   1001 - Session not found
 *   1002 - Session not in waiting state
 *   1003 - Game not active
 *   1004 - Player not in session
 *   1005 - Not your turn
 *   1006 - Illegal move
 *   1007 - Validator error
 */
class GameplayException extends RuntimeException
{
    private int $httpStatus;

    public function __construct(string $message, int $code = 0, int $httpStatus = 400)
    {
        parent::__construct($message, $code);
        $this->httpStatus = $httpStatus;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    // ─────────────────────────────────────────────
    // NAMED CONSTRUCTORS
    // ─────────────────────────────────────────────

    public static function sessionNotFound(string $sessionId): self
    {
        return new self(
            message:    "Game session '{$sessionId}' tidak ditemukan.",
            code:       1001,
            httpStatus: 404
        );
    }

    public static function sessionNotWaiting(string $sessionId, string $currentStatus): self
    {
        return new self(
            message:    "Session '{$sessionId}' tidak dalam status waiting (status: {$currentStatus}).",
            code:       1002,
            httpStatus: 409
        );
    }

    public static function gameNotActive(string $sessionId, string $currentStatus): self
    {
        return new self(
            message:    "Game '{$sessionId}' tidak sedang aktif (status: {$currentStatus}).",
            code:       1003,
            httpStatus: 409
        );
    }

    public static function playerNotInSession(string $userId, string $sessionId): self
    {
        return new self(
            message:    "User '{$userId}' bukan pemain dalam sesi '{$sessionId}'.",
            code:       1004,
            httpStatus: 403
        );
    }

    public static function notYourTurn(string $userId, string $currentTurn): self
    {
        return new self(
            message:    "Bukan giliran user '{$userId}'. Giliran saat ini: {$currentTurn}.",
            code:       1005,
            httpStatus: 422
        );
    }

    public static function illegalMove(string $from, string $to, string $fen): self
    {
        return new self(
            message:    "Move ilegal: {$from} → {$to} dari posisi '{$fen}'.",
            code:       1006,
            httpStatus: 422
        );
    }

    public static function validatorError(int $exitCode, string $output): self
    {
        return new self(
            message:    "Chess validator error (exit {$exitCode}): {$output}",
            code:       1007,
            httpStatus: 500
        );
    }
}
