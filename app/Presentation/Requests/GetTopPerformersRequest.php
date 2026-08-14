<?php

namespace App\Presentation\Requests;

class GetTopPerformersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'serving_type_id' => 'required|integer|exists:serving_types,id',
            'month' => 'nullable|date_format:Y-m',
        ];
    }
}
