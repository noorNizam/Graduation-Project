<?php

namespace App\Infrastructure\Listeners;

use App\Events\MethodExecuted;
use Illuminate\Support\Facades\Log;

class LogMethodExecutionHandler
{
    public function handle(MethodExecuted $event): void
    {
        $logData = [
            'class' => $event->className,
            'method' => $event->methodName,
            'execution_time' => round($event->executionTime, 4),
            'timestamp' => now()->toISOString(),
        ];

        if ($event->exception) {
            Log::error("Method {$event->className}::{$event->methodName} failed", array_merge($logData, [
                'error' => $event->exception->getMessage(),
            ]));
        } else {
            Log::info("Method {$event->className}::{$event->methodName} executed", $logData);
        }
    }
}
