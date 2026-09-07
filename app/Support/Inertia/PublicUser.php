<?php

namespace App\Support\Inertia;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class PublicUser
{
    public static function make(?User $user, string $dashboardUrl): ?array
    {
        if (! $user) {
            return null;
        }

        $photo = $user->getRawOriginal('photo');
        $photoUrl = $photo && $photo !== 'default.jpg' && Storage::disk('public')->exists('images/profile/'.$photo)
            ? Storage::disk('public')->url('images/profile/'.$photo)
            : null;

        $activeRole = session('active_role') ?: $user->roles->first()?->name;

        return [
            'name' => $user->name,
            'role' => strtoupper($activeRole ?? 'user'),
            'photo' => $photoUrl,
            'dashboardUrl' => $dashboardUrl,
        ];
    }
}
