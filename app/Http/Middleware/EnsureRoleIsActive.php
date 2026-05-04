<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('auth.signin-index');
        }

        $activeRole = session('active_role');

        if (! $activeRole) {
            return redirect()->route('auth.select-role');
        }

        if (! $user->hasRole($activeRole)) {
            abort(403, 'Unauthorized active role');
        }

        if (! empty($roles) && ! in_array($activeRole, $roles, true)) {
            abort(403, 'Unauthorized role');
        }

        return $next($request);
    }
}
