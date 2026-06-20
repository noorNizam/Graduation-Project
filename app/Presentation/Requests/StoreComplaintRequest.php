<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'serving_id' => 'required|integer|exists:servings,id',
            'accused_user_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'serving_id.required' => 'Serving ID is required',
            'serving_id.exists' => 'Serving not found',
            'accused_user_id.required' => 'Accused user ID is required',
            'reason.required' => 'Reason is required',
            'reason.min' => 'Reason must be at least 3 characters',
            'description.max' => 'Description must not exceed 1000 characters',
            'attachment.mimes' => 'Attachment must be an image or PDF',
            'attachment.max' => 'Attachment size must not exceed 5MB',
        ];
    }
}
