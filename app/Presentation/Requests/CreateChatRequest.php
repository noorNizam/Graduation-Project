<?php

namespace App\Presentation\Requests;

class CreateChatRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'receiver_id' => ['required_without:type', 'integer', 'exists:users,id'],
            'content' => ['required_with:receiver_id', 'string', 'max:5000'],
            'type' => ['required_without:receiver_id', 'string', 'in:group'],
            'name' => ['required_if:type,group', 'string', 'max:255'],
            'member_ids' => ['required_if:type,group', 'array', 'min:2', 'max:49'],
            'member_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ];
    }
}
