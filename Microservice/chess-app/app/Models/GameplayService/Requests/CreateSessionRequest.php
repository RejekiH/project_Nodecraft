<?php

namespace App\Modules\GameplayService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request pembuatan sesi game baru.
 * Dipanggil oleh RoomService setelah matchmaking.
 */
class CreateSessionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'room_id'       => 'required|string',
            'white_user_id' => 'required|string',
            'black_user_id' => 'required|string|different:white_user_id',
            'time_control'  => 'sometimes|string|regex:/^\d+\+\d+$|^0$/',
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required'              => 'room_id wajib diisi',
            'white_user_id.required'        => 'white_user_id wajib diisi',
            'black_user_id.required'        => 'black_user_id wajib diisi',
            'black_user_id.different'       => 'white_user_id dan black_user_id tidak boleh sama',
            'time_control.regex'            => 'Format time_control harus "menit+increment" (e.g. "10+5") atau "0"',
        ];
    }
}
