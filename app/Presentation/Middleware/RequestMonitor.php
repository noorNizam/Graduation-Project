<?php

namespace App\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $executionTime = microtime(true) - $startTime;
        
        Log::info('HTTP Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => $response->getStatusCode(),
            'execution_time' => round($executionTime, 4),
            'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'timestamp' => now()->toISOString(),
        ]);
        
        return $response;
    }
}