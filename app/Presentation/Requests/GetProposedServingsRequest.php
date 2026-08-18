<?php

namespace App\Presentation\Requests;

class GetProposedServingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'skip' => ['nullable', 'integer', 'min:0'],
            'take' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
