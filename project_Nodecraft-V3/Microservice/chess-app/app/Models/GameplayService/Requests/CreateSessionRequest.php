<?php

namespace App\Modules\GameplayService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/*
|--------------------------------------------------------------------------
| FIX: Regex time_control diganti dengan rule 'in'.
|
| Bug asli: 'regex:/^\d+\+\d+$|^0$/'
|
| Laravel memisahkan validation rules dengan pipe '|'. Ketika regex
| mengandung '|' sebagai alternasi, Laravel menafsirkannya sebagai
| pemisah rule — sehingga rule dipecah menjadi dua:
|   rule 1: 'regex:/^\d+\+\d+$'   (tidak valid, regex tidak ditutup)
|   rule 2: '^0$/'                 (bukan rule yang dikenal)
|
| Dampak: format '0' (unlimited time) selalu gagal validasi →
| game tanpa batas waktu tidak bisa dibuat sama sekali.
|
| Solusi: gunakan rule 'in' dengan daftar nilai eksplisit.
| Ini lebih aman, lebih jelas, dan konsisten dengan CreateRoomRequest
| yang sudah menggunakan pendekatan yang sama.
|--------------------------------------------------------------------------
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

            // FIX: ganti 'regex:/^\d+\+\d+$|^0$/' dengan 'in'
            // Nilai yang valid harus sinkron dengan CreateRoomRequest::rules()
            // agar room dan session selalu punya time_control yang sama.
            'time_control' => [
                'sometimes',
                'string',
                'in:0,1+0,3+0,3+2,5+0,5+3,10+0,10+5,15+10,30+0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required'        => 'room_id wajib diisi',
            'white_user_id.required'  => 'white_user_id wajib diisi',
            'black_user_id.required'  => 'black_user_id wajib diisi',
            'black_user_id.different' => 'white_user_id dan black_user_id tidak boleh sama',
            'time_control.in'         => 'Format time_control tidak valid. Gunakan salah satu: 0, 1+0, 3+0, 5+0, 10+0, 15+10, 30+0',
        ];
    }
}
