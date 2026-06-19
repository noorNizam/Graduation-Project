<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // فقط المدير يقدر يغير حالة الشكوى
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,under_review,resolved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'الحالة مطلوبة',
            'status.in' => 'الحالة غير صالحة',
            'admin_note.max' => 'ملاحظة المدير لا تتجاوز 500 حرف',
        ];
    }
}