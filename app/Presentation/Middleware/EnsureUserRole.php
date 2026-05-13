<?php

namespace App\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Actions considered part of user-only endpoints.
     * Add controller action names here when creating user-only routes.
     * Example: 'App\\Presentation\\Controllers\\ServingController@addPaidServing'
     */
    protected static array $userActions = [
        'App\\Presentation\\Controllers\\ServingController@addPaidServing',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if (! method_exists($user, 'isUser') || ! $user->isUser()) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 403);
        }

        // Prevent deactivated users from accessing protected routes
        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'Account deactivated'], 403);
        }

        return $next($request);
    }
}
