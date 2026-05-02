<?php

namespace App\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if (! method_exists($user, 'isAdmin') || ! $user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 403);
        }

        return $next($request);
    }
}
