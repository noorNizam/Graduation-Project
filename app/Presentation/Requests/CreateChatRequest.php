<?php

namespace App\Presentation\Requests;

class CreateChatRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:personal,group'],
            'receiver_id' => ['required_if:type,personal', 'integer', 'exists:users,id'],
            'content' => ['required_if:type,personal', 'string', 'max:5000'],
            'name' => ['required_if:type,group', 'string', 'max:255'],
            'member_ids' => ['required_if:type,group', 'array', 'min:2', 'max:49'],
            'member_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ];
    }
}
