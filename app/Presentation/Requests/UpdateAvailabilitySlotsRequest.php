<?php

namespace App\Presentation\Requests;

class UpdateAvailabilitySlotsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.day_of_week' => ['nullable', 'integer', 'between:0,6', 'required_without:slots.*.date'],
            'slots.*.date' => ['nullable', 'date_format:Y-m-d', 'required_without:slots.*.day_of_week'],
            'slots.*.start_time' => ['required', 'date_format:H:i:s'],
            'slots.*.end_time' => ['required', 'date_format:H:i:s', 'after:slots.*.start_time'],
            'slots.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
