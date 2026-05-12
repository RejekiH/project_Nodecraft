<?php

namespace App\Modules\UserService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi registrasi user baru
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'email'                 => 'required|email:rfc,dns|max:255',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex'     => 'Username hanya boleh mengandung huruf, angka, dan underscore',
            'username.min'       => 'Username minimal 3 karakter',
            'username.max'       => 'Username maksimal 20 karakter',
            'password.min'       => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
        ];
    }
}
