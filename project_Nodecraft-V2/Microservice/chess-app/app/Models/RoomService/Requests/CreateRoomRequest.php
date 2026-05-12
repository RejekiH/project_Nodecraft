<?php

namespace App\Modules\RoomService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CreateRoomRequest
 *
 * Validasi untuk membuat room baru.
 * Body: { time_control: "5+0" }
 */
class CreateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi ditangani JwtAuthMiddleware
    }

    public function rules(): array
    {
        return [
            'time_control' => [
                'required',
                'string',
                'in:1+0,3+0,3+2,5+0,5+3,10+0,10+5,15+10,30+0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'time_control.required' => 'Time control harus dipilih.',
            'time_control.in'       => 'Time control tidak valid. Pilih salah satu: 1+0, 3+0, 3+2, 5+0, 5+3, 10+0, 10+5, 15+10, 30+0.',
        ];
    }
}
