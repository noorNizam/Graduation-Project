<?php

namespace App\Traits;

use App\Events\MethodExecuted;

trait Loggable
{
    protected function executeWithLogging(string $methodName, callable $operation, array $parameters = [])
    {
        $startTime = microtime(true);
        
        try {
            $result = $operation(...$parameters);
            $executionTime = microtime(true) - $startTime;
            
            event(new MethodExecuted(
                className: static::class,
                methodName: $methodName,
                parameters: $parameters,
                executionTime: $executionTime
            ));
            
            return $result;
        } catch (\Throwable $exception) {
            $executionTime = microtime(true) - $startTime;
            
            event(new MethodExecuted(
                className: static::class,
                methodName: $methodName,
                parameters: $parameters,
                executionTime: $executionTime,
                exception: $exception
            ));
            
            throw $exception;
        }
    }
}