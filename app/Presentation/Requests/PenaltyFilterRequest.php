<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenaltyFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'type' => 'nullable|in:warning,deduct_hours,suspend,ban',
            'user_id' => 'nullable|integer|exists:users,id',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}