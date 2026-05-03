<?php

namespace App\Presentation\Requests;

class UpdatePaidServingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'string'],
            'category_id' => ['sometimes', 'integer', 'exists:serving_categories,id'],
            'cost_amount' => ['sometimes', 'integer'],
            'unit_id' => ['sometimes', 'integer', 'exists:payment_units,id'],
            'location_lat' => ['nullable', 'numeric'],
            'location_lng' => ['nullable', 'numeric'],
            'location_address' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'image', 'max:5120'], // max 5MB
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
