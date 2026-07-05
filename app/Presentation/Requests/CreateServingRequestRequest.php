<?php

namespace App\Presentation\Requests;

class CreateServingRequestRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serving_id' => ['required', 'integer', 'exists:servings,id'],
            'message' => ['nullable', 'string', 'max:1000'],
            'automatically_cancel_after' => ['required', 'integer', 'min:7'],
        ];
    }
}
