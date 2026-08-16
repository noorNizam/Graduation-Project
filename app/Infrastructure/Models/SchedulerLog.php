<?php

namespace App\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerLog extends Model
{
    protected $table = 'scheduler_logs';

    protected $fillable = [
        'job_name',
        'started_at',
        'finished_at',
        'errors',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'errors' => 'array',
    ];
}
