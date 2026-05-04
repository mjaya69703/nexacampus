<?php

namespace App\Http\Middleware;

use App\Models\Settings\System;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemIsInstalled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Kalau tabel systems belum ada
        if (! Schema::hasTable('systems')) {
            abort(500, 'Database belum di migrate. Jalankan: php artisan migrate');
        }

        $system = System::first();

        // Kalau belum setup, redirect ke halaman setup
        if (! $system || ! $system->is_installed) {

            // izinkan akses halaman setup
            if ($request->is('setup*')) {
                return $next($request);
            }

            return redirect()->route('system.setup-wizard');
        }

        // Kalau sudah setup tapi user buka setup lagi
        if ($system->is_installed && $request->is('setup*')) {
            return redirect()->route('auth.signin-index');
        }

        return $next($request);
    }
}
