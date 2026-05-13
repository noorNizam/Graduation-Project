<?php

namespace App\Presentation\Requests;

class SearchUsersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'in:male,female,not specified'],
            'skip' => ['nullable', 'integer', 'min:0'],
            'take' => ['nullable', 'integer', 'min:1', 'max:100'],
            'current_job' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'gender.in' => 'Gender must be one of: male, female, not specified',
            'take.max' => 'Maximum take value is 100',
        ];
    }
}
