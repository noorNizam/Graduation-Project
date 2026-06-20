<?php

namespace App\Presentation\Requests;

class CreatePaymentUnitRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:payment_units,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The unit name is required',
            'name.unique' => 'This payment unit already exists',
        ];
    }
}
