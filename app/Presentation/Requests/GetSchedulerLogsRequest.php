<?php

namespace App\Presentation\Requests;

class GetSchedulerLogsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'job_name' => 'nullable|string|max:255',
            'skip' => 'nullable|integer|min:0',
            'take' => 'nullable|integer|min:1|max:100',
        ];
    }
}
