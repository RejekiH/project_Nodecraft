<?php

namespace App\Modules\GameplayService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request resign.
 */
class ResignRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'user_id' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'user_id wajib diisi',
        ];
    }
}
