<?php

namespace App\Presentation\Requests;

class GetCategoriesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:serving_categories,id'],
        ];
    }
}
