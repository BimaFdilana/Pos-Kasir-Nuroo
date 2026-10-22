<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(403, 'Forbidden for this role.');
            }
            abort(403, 'Forbidden for this role.');
        }

        return $next($request);
    }
}
