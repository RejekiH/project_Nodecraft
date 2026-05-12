<?php

namespace App\Modules\BackupService\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi request apply match result batch
 */
class ApplyMatchResultRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'results'            => 'required|array|min:1|max:10',
            'results.*.user_id'  => 'required|string',
            'results.*.result'   => 'required|string|in:win,loss,draw',
            'results.*.preview'  => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'results.required'          => 'Field results wajib diisi',
            'results.*.result.in'       => 'Nilai result harus win, loss, atau draw',
            'results.*.user_id.required' => 'user_id wajib diisi untuk setiap hasil',
        ];
    }
}
