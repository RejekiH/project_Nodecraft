<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'login'    => 'required|string|max:255', // username atau email
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required'    => 'Username atau email diperlukan',
            'password.required' => 'Password diperlukan',
        ];
    }
}
