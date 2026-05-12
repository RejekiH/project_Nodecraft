<?php

namespace App\Modules\GameplayService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request submit move.
 */
class SubmitMoveRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'user_id'       => 'required|string',
            'from'          => ['required', 'string', 'regex:/^[a-h][1-8]$/'],
            'to'            => ['required', 'string', 'regex:/^[a-h][1-8]$/'],
            'promotion'     => 'sometimes|nullable|string|in:q,r,b,n',
            'time_spent_ms' => 'sometimes|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'   => 'user_id wajib diisi',
            'from.required'      => 'Square asal (from) wajib diisi',
            'from.regex'         => 'Square harus dalam format algebraic (a1-h8)',
            'to.required'        => 'Square tujuan (to) wajib diisi',
            'to.regex'           => 'Square harus dalam format algebraic (a1-h8)',
            'promotion.in'       => 'Promotion harus salah satu dari: q, r, b, n',
        ];
    }
}
