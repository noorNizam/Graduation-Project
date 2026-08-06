<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['api', 'auth:sanctum'], 'prefix' => 'api'],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // as AOP implementation we register the request monitoring middleware
        // for all incoming requests
        $middleware->web(append: [
            \App\Presentation\Middleware\RequestMonitor::class,
        ]);

        $middleware->api(append: [
            \App\Presentation\Middleware\RequestMonitor::class,
        ]);

        $middleware->alias([
            'ensure.admin' => \App\Presentation\Middleware\EnsureAdminRole::class,
            'ensure.user' => \App\Presentation\Middleware\EnsureUserRole::class,
        ]);

        $middleware->trustProxies(
            '*',
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle missing token or invalid token for API routes
        $exceptions->render(function (\Exception $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                // Handle AuthenticationException (thrown when token is invalid/missing)
                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated',
                    ], 401);
                }

                // Handle RouteNotFoundException (thrown when redirect to login route fails)
                if ($e instanceof \Symfony\Component\Routing\Exception\RouteNotFoundException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated',
                    ], 401);
                }
            }
        });
    })->create();
