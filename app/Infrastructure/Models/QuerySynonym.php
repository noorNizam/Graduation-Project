<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class QuerySynonym extends Model
{
    protected $table = 'query_synonyms';

    protected $fillable = [
        'word',
        'language',
        'synonyms',
    ];

    protected $casts = [
        'synonyms' => 'array',
    ];
}
