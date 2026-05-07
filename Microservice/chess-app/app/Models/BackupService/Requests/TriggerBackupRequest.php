<?php

namespace App\Modules\BackupService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request trigger backup manual
 */
class TriggerBackupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|string|in:full,incremental',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipe backup harus "full" atau "incremental"',
        ];
    }
}
