<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ServingType extends Model
{
    protected $table = 'serving_types';

    protected $fillable = [
        'name',
    ];
}
