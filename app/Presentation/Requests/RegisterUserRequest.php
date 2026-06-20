<?php

namespace App\Presentation\Requests;

use Illuminate\Validation\Rule;

class RegisterUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'current_job' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', 'in:male,female,not specified'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/'],
            'phone' => ['nullable', 'regex:/^(\+963|0)?9\d{8}$/'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'profile_picture' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'otp' => ['required', 'string', 'size:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered',
            'otp.required' => 'OTP verification code is required',
            'otp.size' => 'OTP must be exactly 6 digits',
            'password.regex' => 'Password must include letters, numbers, and special characters',
            'phone.regex' => 'Phone number must be a valid Syrian mobile number',
        ];
    }
}
