<?php

namespace App\Modules\GameplayService\Services;

use App\Modules\GameplayService\Exceptions\GameplayException;
use Illuminate\Support\Facades\Log;

/**
 * ChessValidator
 *
 * Memvalidasi legalitas move catur berdasarkan posisi FEN.
 *
 * Strategi validasi:
 *   Validasi dilakukan dengan memanggil Node.js script yang menggunakan
 *   library chess.js — library JavaScript paling andal untuk chess logic.
 *   Ini lebih efisien daripada reimplementasi rules catur di PHP.
 *
 *   Alternatif (jika Node.js tidak tersedia): php-chess library.
 *
 * Output validate():
 * {
 *   "legal":        true,
 *   "san":          "e4",          // Standard Algebraic Notation
 *   "fen":          "...",         // FEN setelah move
 *   "is_check":     false,
 *   "is_checkmate": false,
 *   "is_stalemate": false,
 *   "is_draw":      false,         // draw by repetition / 50-move rule / insufficient material
 *   "captured":     null | "p"     // piece yang ditangkap (jika ada)
 * }
 */
class ChessValidator
{
    private string $nodeBin;
    private string $scriptPath;
    private int    $timeout;

    public function __construct()
    {
        $this->nodeBin    = config('gameplay.node_binary', 'node');
        $this->scriptPath = config('gameplay.chess_validator_script',
            base_path('scripts/chess_validator.js'));
        $this->timeout    = config('gameplay.validator_timeout', 5);
    }

    // ─────────────────────────────────────────────
    // VALIDATE
    // ─────────────────────────────────────────────

    /**
     * Validasi move dan kembalikan informasi posisi baru.
     *
     * @param string      $fen        Posisi saat ini (FEN)
     * @param string      $from       Square asal, e.g. 'e2'
     * @param string      $to         Square tujuan, e.g. 'e4'
     * @param string|null $promotion  Piece promosi: 'q'|'r'|'b'|'n'
     *
     * @return array
     * @throws GameplayException
     */
    public function validate(string $fen, string $from, string $to, ?string $promotion = null): array
    {
        $input = json_encode([
            'fen'       => $fen,
            'from'      => $from,
            'to'        => $to,
            'promotion' => $promotion,
        ]);

        $cmd    = sprintf(
            '%s %s %s 2>&1',
            escapeshellcmd($this->nodeBin),
            escapeshellarg($this->scriptPath),
            escapeshellarg($input)
        );

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $raw = implode('', $output);

        if ($exitCode !== 0) {
            Log::error('[ChessValidator] Script error', [
                'exit_code' => $exitCode,
                'output'    => $raw,
            ]);
            throw GameplayException::validatorError($exitCode, $raw);
        }

        $result = json_decode($raw, true);
        if (!is_array($result)) {
            throw GameplayException::validatorError(0, 'Output tidak valid JSON: ' . $raw);
        }

        return $result;
    }

    // ─────────────────────────────────────────────
    // LEGAL MOVES
    // ─────────────────────────────────────────────

    /**
     * Dapatkan semua move legal dari posisi FEN saat ini.
     * Berguna untuk UI client (highlight possible moves).
     *
     * @param string $fen
     * @return array  Array of { from, to, san }
     * @throws GameplayException
     */
    public function getLegalMoves(string $fen): array
    {
        $input = json_encode([
            'fen'    => $fen,
            'action' => 'legal_moves',
        ]);

        $cmd = sprintf(
            '%s %s %s 2>&1',
            escapeshellcmd($this->nodeBin),
            escapeshellarg($this->scriptPath),
            escapeshellarg($input)
        );

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $raw    = implode('', $output);
        $result = json_decode($raw, true);

        if ($exitCode !== 0 || !is_array($result)) {
            Log::warning('[ChessValidator] getLegalMoves error', ['raw' => $raw]);
            return [];
        }

        return $result['moves'] ?? [];
    }
}
