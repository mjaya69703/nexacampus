<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

class ActivePermission
{
    public static function check(string $permission, ?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        $activeRole = session('active_role');

        if (! $activeRole) {
            return false;
        }

        $role = $user->roles()
            ->where('name', $activeRole)
            ->first();

        if (! $role) {
            return false;
        }

        return $role->permissions()
            ->where('name', $permission)
            ->exists();
    }

    public static function any(array $permissions, ?Authenticatable $user = null): bool
    {
        foreach ($permissions as $permission) {
            if (static::check($permission, $user)) {
                return true;
            }
        }

        return false;
    }

    public static function all(array $permissions, ?Authenticatable $user = null): bool
    {
        foreach ($permissions as $permission) {
            if (! static::check($permission, $user)) {
                return false;
            }
        }

        return true;
    }
}
