<?php

namespace App\Presentation\Requests;

class UpdateCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
