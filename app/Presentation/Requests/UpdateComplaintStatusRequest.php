<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,under_review,resolved,rejected',
            'admin_note' => 'nullable|string|max:500',
            'escrow_action' => 'nullable|string|in:release_to_owner,refund_to_requester',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Status is invalid',
            'admin_note.max' => 'Admin note must not exceed 500 characters',
            'escrow_action.in' => 'Escrow action is invalid',
        ];
    }
}
