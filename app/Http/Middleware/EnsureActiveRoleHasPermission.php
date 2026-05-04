<?php

namespace App\Http\Middleware;

use App\Support\ActivePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveRoleHasPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('auth.signin-index');
        }

        $activeRole = session('active_role');

        if (! $activeRole) {
            return redirect()->route('auth.select-role');
        }

        $requiredPermissions = collect(explode('|', $permissions))
            ->map(fn (string $permission) => trim($permission))
            ->filter()
            ->values()
            ->all();

        if (empty($requiredPermissions)) {
            return $next($request);
        }

        if (! ActivePermission::any($requiredPermissions, $user)) {
            abort(403, 'Unauthorized permission for active role');
        }

        return $next($request);
    }
}
