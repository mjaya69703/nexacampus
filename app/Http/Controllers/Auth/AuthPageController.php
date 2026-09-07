<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Support\Inertia\PublicUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthPageController extends Controller
{
    public function login(): Response
    {
        return Inertia::render('Auth/Login', $this->sharedProps());
    }

    public function authenticate(Request $request): RedirectResponse|SymfonyResponse
    {
        $validated = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'remember' => 'sometimes|boolean',
        ]);

        $login = $validated['login'];
        $password = $validated['password'];
        $remember = (bool) ($validated['remember'] ?? false);

        $system = Cache::remember('global_system', 3600, fn () => System::first());
        $maxLoginAttempts = $system->max_login_attempts ?? 5;
        $decaySeconds = $system->login_decay_seconds ?? 300;

        $key = 'login:'.Str::lower($login).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, $maxLoginAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = (int) ceil($seconds / 60);

            activity()
                ->useLog('auth')
                ->withProperties([
                    'login' => $login,
                    'field_type' => filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reason' => 'rate_limited',
                    'max_attempts' => $maxLoginAttempts,
                    'available_in_seconds' => $seconds,
                ])
                ->log('login blocked');

            return back()->withErrors([
                'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$minutes} menit.",
            ])->onlyInput('login');
        }

        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$fieldType => $login, 'password' => $password], $remember)) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            $user = Auth::user();
            $user->last_login_at = now();
            $user->save();

            $roles = $user->getRoleNames();

            activity()
                ->causedBy($user)
                ->useLog('auth')
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ])
                ->log('login');

            if ($roles->count() === 1) {
                session(['active_role' => $roles->first()]);

                activity()
                    ->causedBy($user)
                    ->useLog('auth')
                    ->withProperties([
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'active_role' => $roles->first(),
                    ])
                    ->log('selected active role');

                // Dashboard adalah halaman Livewire (bukan Inertia) → pakai
                // 409 X-Inertia-Location agar browser pindah URL betulan,
                // bukan kejebak error-modal berisi HTML dashboard.
                return Inertia::location(route($user->prefix.'dashboard.index'));
            }

            return redirect()->route('auth.select-role');
        }

        RateLimiter::hit($key, $decaySeconds);

        return back()->withErrors([
            'login' => 'Username / Email atau password salah.',
        ])->onlyInput('login');
    }

    public function selectRole(Request $request): Response
    {
        $user = $request->user();
        $roles = $user->getRoleNames()->values()->all();

        return Inertia::render('Auth/SelectRole', array_merge($this->sharedProps($user), [
            'roles' => $roles,
            'roleMeta' => $this->roleMeta(),
            'user' => PublicUser::make($user, $this->dashboardUrl($user)),
        ]));
    }

    public function storeRole(Request $request): RedirectResponse|SymfonyResponse
    {
        $validated = $request->validate([
            'role' => 'required|string',
        ]);

        $user = $request->user();
        $roles = $user->getRoleNames();
        $selectedRole = strtolower($validated['role']);

        if (! $roles->map(fn ($role) => strtolower($role))->contains($selectedRole)) {
            return back()->withErrors(['role' => 'Role yang dipilih tidak valid.']);
        }

        // Simpan nilai asli (case sesuai DB) agar konsisten dengan session lama.
        $canonical = $roles->first(fn ($role) => strtolower($role) === $selectedRole) ?? $selectedRole;
        session(['active_role' => $canonical]);

        activity()
            ->causedBy($user)
            ->useLog('auth')
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'active_role' => $canonical,
            ])
            ->log('selected active role');

        session()->flash('success', 'Role berhasil dipilih: '.ucfirst($canonical));

        // Lihat catatan di authenticate(): dashboard Livewire wajib full visit.
        return Inertia::location(route($user->prefix.'dashboard.index'));
    }

    /**
     * @return array{campus: array{name: string, logo: string, description: string}, links: array<string,string>, user: null}
     */
    private function sharedProps(?\App\Models\User $user = null): array
    {
        return [
            'campus' => [
                'name' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
                'logo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
                'description' => System::value('app_description') ?? 'Sistem informasi akademik perguruan tinggi terpadu.',
            ],
            'links' => [
                'login' => route('auth.signin-index'),
                'admission' => route('root.admission.apply'),
                'admissionStatus' => route('root.admission.status'),
                'tuition' => route('root.admission.tuition'),
                'requirements' => route('root.admission.requirements'),
                'faq' => route('root.faq'),
                'contact' => route('root.kontak'),
                'announcements' => route('root.publication.announcements'),
            ],
            'user' => $user ? PublicUser::make($user, $this->dashboardUrl($user)) : null,
        ];
    }

    private function dashboardUrl(\App\Models\User $user): string
    {
        // Untuk halaman auth, dashboardUrl hanya dipakai avatar/menu — fallback ke select-role.
        $dashboardRoute = $user->prefix.'dashboard.index';

        return $dashboardRoute && Route::has($dashboardRoute)
            ? route($dashboardRoute)
            : route('auth.select-role');
    }

    /**
     * @return array<string, array{desc: string, icon: string}>
     */
    private function roleMeta(): array
    {
        return [
            'superuser' => ['desc' => 'Akses superuser dengan semua izin', 'icon' => 'shield'],
            'admin' => ['desc' => 'Akses penuh ke sistem', 'icon' => 'shield'],
            'lecturer' => ['desc' => 'Akses dosen & pengajaran', 'icon' => 'presentation'],
            'academic-leader' => ['desc' => 'Akses pemantauan akademik sesuai jabatan', 'icon' => 'briefcase'],
            'student' => ['desc' => 'Akses mahasiswa aktif', 'icon' => 'graduation'],
            'alumni' => ['desc' => 'Akses khusus alumni', 'icon' => 'users'],
        ];
    }
}
