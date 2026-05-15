<?php

namespace App\Modules\UserService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request login
 * 
 * Field "login" bisa berupa username atau email.
 * File ini sebelumnya KOSONG — sudah diperbaiki.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'login'    => 'required|string|min:3|max:255',
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required'    => 'Username atau email wajib diisi',
            'login.min'         => 'Username atau email minimal 3 karakter',
            'password.required' => 'Password wajib diisi',
            'password.min'      => 'Password minimal 8 karakter',
        ];
    }
}
