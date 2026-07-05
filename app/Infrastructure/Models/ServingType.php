<?php

namespace App\Infrastructure\Models;

use Database\Factories\ServingTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServingType extends Model
{
    use HasFactory;

    protected static function newFactory(): ServingTypeFactory
    {
        return ServingTypeFactory::new();
    }

    protected $table = 'serving_types';

    protected $fillable = [
        'name',
    ];
}
