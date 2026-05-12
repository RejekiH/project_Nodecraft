<?php

namespace App\Modules\UserService\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email'                     => 'sometimes|email:rfc|max:255',
            'current_password'          => 'sometimes|string',
            'new_password'              => 'sometimes|string|min:8|confirmed',
            'new_password_confirmation' => 'sometimes|string',
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.min'       => 'Password baru minimal 8 karakter',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok',
        ];
    }
}
