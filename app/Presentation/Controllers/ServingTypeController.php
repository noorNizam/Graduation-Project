<?php

namespace App\Presentation\Controllers;

use App\Infrastructure\Models\ServingType;

class ServingTypeController
{
    public function getAll()
    {
        return response()->json([
            'success' => true,
            'data' => ServingType::all(['id', 'name']),
        ]);
    }
}
