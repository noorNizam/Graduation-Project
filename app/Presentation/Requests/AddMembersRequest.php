<?php

namespace App\Presentation\Requests;

class AddMembersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:10'],
            'user_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ];
    }
}
