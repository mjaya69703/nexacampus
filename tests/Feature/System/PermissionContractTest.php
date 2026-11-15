<?php

use App\Support\ResourceRegistry;

/**
 * Kontrak permission.
 *
 * Permission yang diperiksa kode tetapi tidak pernah dibuat oleh
 * `permissions:sync` selalu bernilai false untuk semua role — tombol
 * hilang dan aksi diam-diam ditolak. Test ini memastikan seluruh
 * permission yang dirujuk memang terdaftar di `config/resources.php`.
 */
function permissionContractFiles(): array
{
    $files = [];

    foreach ([app_path(), resource_path('views'), base_path('routes')] as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

function permissionContractDefined(): array
{
    $defined = [];

    foreach (ResourceRegistry::all() as $resource) {
        foreach (ResourceRegistry::permissionsFor($resource) as $permission) {
            $defined[] = $permission;
        }
    }

    return array_values(array_unique($defined));
}

function permissionContractReferences(): array
{
    $references = [];

    foreach (permissionContractFiles() as $path) {
        $content = file_get_contents($path);

        // ActivePermission::check('x.y') / ::any([...]) / @activecan*([...])
        if (preg_match_all('/(?:ActivePermission::(?:check|any|all)|@activecan(?:any|all)?)\s*\((.*?)\)/s', $content, $calls, PREG_SET_ORDER)) {
            foreach ($calls as $call) {
                if (preg_match_all("/'([a-z0-9\-]+\.[a-zA-Z]+)'/", $call[1], $permissions)) {
                    foreach (array_unique($permissions[1]) as $permission) {
                        $references[$permission][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                    }
                }
            }
        }

        // Middleware: active_permission:x.y
        if (preg_match_all('/active_permission:([a-z0-9\-]+\.[a-zA-Z]+)/', $content, $middleware)) {
            foreach (array_unique($middleware[1]) as $permission) {
                $references[$permission][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }
    }

    return $references;
}

it('hanya memeriksa permission yang terdaftar di config/resources.php', function () {
    $defined = permissionContractDefined();
    $references = permissionContractReferences();

    $missing = collect($references)
        ->reject(fn (array $files, string $permission) => in_array($permission, $defined, true))
        ->map(fn (array $files, string $permission) => $permission.' ← '.implode(', ', array_unique($files)))
        ->values()
        ->all();

    expect($missing)->toBe([], "Permission berikut dipakai kode tetapi tidak didefinisikan:\n".implode("\n", $missing));
});
