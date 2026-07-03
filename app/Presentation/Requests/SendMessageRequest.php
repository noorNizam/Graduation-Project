<?php

namespace App\Presentation\Requests;

class SendMessageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
        ];
    }
}
