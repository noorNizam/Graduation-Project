<?php

namespace App\Presentation\Requests;

class GetServingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'serving_type_id' => 'nullable|integer|exists:serving_types,id',
            'payment_unit_id' => 'nullable|integer|exists:payment_units,id',
            'serving_category_id' => 'nullable|integer|exists:serving_categories,id',
            'skip' => 'nullable|integer|min:0',
            'take' => 'nullable|integer|min:1|max:100',
            'name' => 'nullable|string|max:255',
        ];
    }
}
