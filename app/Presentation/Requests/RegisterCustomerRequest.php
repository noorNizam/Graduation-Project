<?php

namespace App\Presentation\Requests;

use App\Infrastructure\Models\User;
use Illuminate\Validation\Rule;

class RegisterCustomerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'otp' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered',
            'otp.required' => 'OTP verification code is required',
            'otp.size' => 'OTP must be exactly 6 digits',
        ];
    }
}
