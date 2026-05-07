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
 *   result:     'white' | 'black' | 'draw',
 *   end_reason: 'checkmate' | 'timeout' | 'resign' | 'draw_agreement',
 *   winner_id?: string,
 *   pgn?:       array
 * }
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
            'result'     => ['required', 'string', 'in:white,black,draw'],
            'end_reason' => ['required', 'string', 'in:checkmate,timeout,resign,draw_agreement'],
            'winner_id'  => ['nullable', 'string'],
            'pgn'        => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'result.required'     => 'Hasil pertandingan harus diisi.',
            'result.in'           => 'Hasil tidak valid. Pilihan: white, black, draw.',
            'end_reason.required' => 'Alasan akhir pertandingan harus diisi.',
            'end_reason.in'       => 'End reason tidak valid. Pilihan: checkmate, timeout, resign, draw_agreement.',
        ];
    }
}
