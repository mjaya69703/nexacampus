<?php

namespace App\Http\Middleware;

use App\Support\Financial\FinancialClearanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinancialClearance
{
    public function handle(Request $request, Closure $next, string ...$holdTypes): Response
    {
        $studentProfile = $request->user()?->studentProfile;
        $holdTypes = ! empty($holdTypes) ? $holdTypes : $this->holdTypesForRoute($request);

        if (! $studentProfile || empty($holdTypes)) {
            return $next($request);
        }

        if (app(FinancialClearanceService::class)->hasBlockingHold($studentProfile, $holdTypes)) {
            return redirect()
                ->route('student.financial.invoices')
                ->with('error', 'Akses sementara ditahan karena ada tagihan overdue yang melewati masa tenggang. Selesaikan pembayaran atau hubungi finance untuk dispensasi.');
        }

        return $next($request);
    }

    private function holdTypesForRoute(Request $request): array
    {
        $routeName = $request->route()?->getName();

        if (! $routeName || ! str_starts_with($routeName, 'student.')) {
            return [];
        }

        return collect(config('financial.hold_targets', []))
            ->filter(function (array $target) use ($routeName): bool {
                return collect($target['routes'] ?? [])
                    ->contains(fn (string $pattern) => str($routeName)->is($pattern));
            })
            ->keys()
            ->values()
            ->all();
    }
}
