<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MethodExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $className,
        public string $methodName,
        public array $parameters,
        public float $executionTime,
        public ?\Throwable $exception = null
    ) {}
}