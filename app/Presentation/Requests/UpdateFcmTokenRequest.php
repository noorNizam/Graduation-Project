<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFcmTokenRequest extends FormRequest
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