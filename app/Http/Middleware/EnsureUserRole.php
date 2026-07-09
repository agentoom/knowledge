<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Forbidden. You do not have the required role to access this area.');
        }

        $userRole = $user->role?->value;

        if (! in_array($userRole, $roles, true)) {
            abort(403, 'Forbidden. You do not have the required role to access this area.');
        }

        return $next($request);
    }
}
