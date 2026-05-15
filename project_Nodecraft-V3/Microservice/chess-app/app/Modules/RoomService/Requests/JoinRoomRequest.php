<?php

namespace App\Modules\RoomService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * JoinRoomRequest
 *
 * Validasi untuk join room.
 * Body: { code: "AB3X9Z" }
 */
class JoinRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'size:6',
                'regex:/^[A-Z2-9]{6}$/i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode room harus diisi.',
            'code.size'     => 'Kode room harus 6 karakter.',
            'code.regex'    => 'Format kode room tidak valid.',
        ];
    }
}
