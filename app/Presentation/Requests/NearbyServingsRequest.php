<?php

namespace App\Presentation\Requests;

class NearbyServingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'skip' => ['nullable', 'integer', 'min:0'],
            'take' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
