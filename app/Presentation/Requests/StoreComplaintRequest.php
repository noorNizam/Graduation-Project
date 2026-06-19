<?php

namespace App\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{
    /**
     * تحديد إذا كان المستخدم مصرح له بهذا الطلب
     */
    public function authorize(): bool
    {
        // أي مستخدم مسجل دخول يقدر يقدم شكوى
        return auth()->check();
    }

    /**
     * قواعد التحقق من صحة البيانات
     */
    public function rules(): array
    {
        return [
            'serving_id' => 'required|integer|exists:servings,id',
            'accused_user_id' => 'required|integer|exists:users,id|different:complainant_id',
            'reason' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * رسائل الخطأ المخصصة (اختياري)
     */
    public function messages(): array
    {
        return [
            'serving_id.required' => 'رقم الخدمة مطلوب',
            'serving_id.exists' => 'الخدمة غير موجودة',
            'accused_user_id.required' => 'رقم المستخدم المشتكى عليه مطلوب',
            'accused_user_id.different' => 'لا يمكنك تقديم شكوى على نفسك',
            'reason.required' => 'سبب الشكوى مطلوب',
            'reason.min' => 'سبب الشكوى يجب أن يكون 3 أحرف على الأقل',
            'description.max' => 'الوصف لا يتجاوز 1000 حرف',
            // 🔥 مرفق واحد اختياري
        'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }
}