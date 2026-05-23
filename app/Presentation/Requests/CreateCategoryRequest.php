<?php

namespace App\Presentation\Requests;

class CreateCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:serving_categories,id'],
        ];
    }
}
