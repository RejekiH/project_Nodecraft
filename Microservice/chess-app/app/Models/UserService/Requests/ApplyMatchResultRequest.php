<?php

namespace App\Modules\UserService\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyMatchResultRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'result'                    => 'required|in:win,loss,draw',
            'preview'                   => 'sometimes|array',
            'preview.match_id'          => 'sometimes|string',
            'preview.opponent_username' => 'sometimes|string',
            'preview.result'            => 'sometimes|string',
            'preview.fen_final'         => 'sometimes|string',
            'preview.moves_count'       => 'sometimes|integer',
        ];
    }
}
