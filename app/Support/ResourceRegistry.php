<?php

namespace App\Support;

class ResourceRegistry
{
    public static function all(): array
    {
        return config('resources', []);
    }

    public static function permissionMap(): array
    {
        return [
            'index' => 'viewAny',
            'show' => 'view',
            'create' => 'create',
            'edit' => 'update',
            'delete' => 'delete',
        ];
    }

    public static function permissionsFor(array $resource): array
    {
        if (! empty($resource['permissions']) && is_array($resource['permissions'])) {
            return collect($resource['permissions'])
                ->filter(fn (mixed $permission) => is_string($permission) && $permission !== '')
                ->unique()
                ->map(fn (string $permission) => "{$resource['resource']}.{$permission}")
                ->values()
                ->all();
        }

        $map = static::permissionMap();

        return collect($resource['actions'] ?? [])
            ->map(fn (string $action) => $map[$action] ?? null)
            ->filter()
            ->unique()
            ->map(fn (string $permission) => "{$resource['resource']}.{$permission}")
            ->values()
            ->all();
    }

    public static function menuPermission(array $resource): ?string
    {
        $permissions = static::permissionsFor($resource);

        return collect($permissions)
            ->first(fn (string $permission) => str_ends_with($permission, '.viewAny'));
    }
}
