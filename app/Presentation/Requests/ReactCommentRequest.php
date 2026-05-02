<?php

namespace App\Presentation\Requests;

class ReactCommentRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:like,dislike'],
        ];
    }
}
