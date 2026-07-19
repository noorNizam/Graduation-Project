<?php

namespace App\Presentation\Requests;

class RateServingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['required', 'numeric', 'min:0', 'max:5'],
        ];
    }
}
