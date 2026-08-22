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
            // Admin-settable statuses only: the four-state vocabulary below
            // is final — statuses outside it were removed entirely.
            'status' => 'required|in:pending,awaiting_documents,under_review,resolved',
            'admin_note' => 'nullable|string|max:500',
            // Who the resolution favored. Required context for the penalty
            // listener (penalties fire only on 'justified') and for escrow:
            // justified refunds the requester, unjustified releases the owner.
            'outcome' => 'nullable|string|in:justified,unjustified',
            'documents_requested_from' => 'nullable|string|in:complainant,accused,both',
            'documents_due_at' => 'nullable|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.in' => 'Status is invalid',
            'admin_note.max' => 'Admin note must not exceed 500 characters',
            'outcome.in' => 'Outcome is invalid',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('status') === 'resolved'
                && ! $this->filled('outcome')) {
                $validator->errors()->add(
                    'outcome',
                    'Outcome (justified or unjustified) is required when resolving a complaint.'
                );
            }
        });
    }
}
