<?php

namespace App\Presentation\Requests;

class UpdateGroupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
