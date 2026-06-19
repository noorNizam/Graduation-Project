<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComplaintFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:pending,under_review,resolved,rejected',
            'complainant_id' => 'nullable|integer|exists:users,id',
            'accused_user_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}