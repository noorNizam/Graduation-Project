<?php

namespace App\Presentation\Requests;

class UpdateProfileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'max:100'],
            'current_job' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'gender' => ['sometimes', 'in:male,female,not specified'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'profile_picture' => ['sometimes', 'file', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'profile_picture.image' => 'The profile picture must be an image file',
            'profile_picture.mimes' => 'The profile picture must be a file of type: jpeg, png, jpg, gif, webp',
            'profile_picture.max' => 'The profile picture must not be greater than 5 MB',
            'gender.in' => 'Gender must be one of: male, female, not specified',
        ];
    }
}
