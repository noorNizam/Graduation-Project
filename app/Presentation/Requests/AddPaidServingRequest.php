<?php

namespace App\Presentation\Requests;

use Illuminate\Validation\Rule;

class AddPaidServingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'category_id' => ['required', 'integer', 'exists:serving_categories,id'],
            'cost_amount' => ['required', 'integer'],
            'unit_id' => ['required', 'integer', 'exists:payment_units,id'],
            'location_lat' => ['nullable', 'numeric'],
            'location_lng' => ['nullable', 'numeric'],
            'location_address' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'image', 'max:5120'], // max in KB (5 MB)
        ];
    }

    public function messages(): array
    {
        return [
            'image.max' => 'The image must not be greater than 5 MB',
            'image.image' => 'The uploaded file must be an image',
        ];
    }
}
