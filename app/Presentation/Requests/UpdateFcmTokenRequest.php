<?php

namespace App\Presentation\Requests;

class UpdateFcmTokenRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'fcm_token' => 'required|string',
        ];
    }
}