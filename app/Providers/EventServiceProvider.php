<?php

namespace App\Providers;

use App\Events\MethodExecuted;
use App\Infrastructure\Listeners\LogMethodExecutionHandler;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MethodExecuted::class => [
            LogMethodExecutionHandler::class,
        ],
    ];
}   