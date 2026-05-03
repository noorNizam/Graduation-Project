<?php

namespace App\Presentation\Requests;

class AcceptServingRequestRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
