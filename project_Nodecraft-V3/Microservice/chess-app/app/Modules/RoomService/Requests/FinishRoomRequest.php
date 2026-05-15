<?php

namespace App\Modules\RoomService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FinishRoomRequest
 *
 * Validasi untuk endpoint internal finish room.
 * Dipanggil oleh GameplayService setelah pertandingan selesai.
 *
 * Body: {
 *   result:     'white' | 'black' | 'draw' | 'white_wins' | 'black_wins',
 *   end_reason: 'checkmate' | 'timeout' | 'resign' | 'draw_agreement' | 'stalemate' | 'draw_rule',
 *   winner_id?: string,
 *   pgn?:       array,
 *   session_id?: string   — match_id dari GameplayService, untuk preview
 * }
 *
 * CATATAN: 'white_wins' dan 'black_wins' adalah alias yang dikirim GameplayService.
 * Normalisasi ke 'white'/'black' dilakukan di RoomService::finishRoom().
 */
class FinishRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via InternalApiKeyMiddleware
    }

    public function rules(): array
    {
        return [
            'result'     => ['required', 'string', 'in:white,black,draw,white_wins,black_wins'],
            'end_reason' => ['required', 'string', 'in:checkmate,timeout,resign,draw_agreement,stalemate,draw_rule'],
            'winner_id'  => ['nullable', 'string'],
            'pgn'        => ['nullable', 'array'],
            'session_id' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'result.required'     => 'Hasil pertandingan harus diisi.',
            'result.in'           => 'Hasil tidak valid. Pilihan: white, black, draw, white_wins, black_wins.',
            'end_reason.required' => 'Alasan akhir pertandingan harus diisi.',
            'end_reason.in'       => 'End reason tidak valid. Pilihan: checkmate, timeout, resign, draw_agreement, stalemate, draw_rule.',
        ];
    }
}
