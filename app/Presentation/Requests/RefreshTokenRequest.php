<?php

namespace App\Presentation\Requests;

class RefreshTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }
}
