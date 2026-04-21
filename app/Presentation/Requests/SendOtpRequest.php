<?php

namespace App\Presentation\Requests;

use Illuminate\Validation\Rule;

class SendOtpRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.max' => 'Email address must not exceed 100 characters',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email address',
        ];
    }
}