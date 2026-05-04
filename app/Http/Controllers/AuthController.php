<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function logout()
    {
        activity()
            ->causedBy(auth()->user())
            ->useLog('auth')
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'active_role' => session('active_role'),
            ])
            ->log('logout');

        Auth::logout();

        session()->forget([
            'active_role',
            'active_subrole',
        ]);

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        session()->flash('success', 'You have been logged out successfully.');

        return redirect()->route('auth.signin-index');
    }

    public function switchRole()
    {
        Session::forget('active_role');

        activity()
            ->causedBy(auth()->user())
            ->useLog('auth')
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'active_role' => session('active_role'),
            ])
            ->log('switched active role');

        session()->flash('info', 'Silahkan pilih role yang ingin Anda gunakan.');

        return redirect()->route('auth.select-role');
    }
}
