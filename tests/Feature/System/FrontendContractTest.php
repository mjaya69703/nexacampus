<?php

use Illuminate\Support\Facades\Route;

/**
 * Kontrak frontend ↔ backend.
 *
 * Bug yang pernah terjadi: controller me-render halaman Inertia
 * `Admin/Campus/Asset/Trash` yang tidak pernah dibuat, sehingga tab
 * "Sampah" gagal total di browser meskipun seluruh feature test hijau
 * (assertInertia tidak memeriksa keberadaan file .tsx).
 *
 * Test ini memastikan setiap komponen yang dirujuk backend benar-benar ada.
 */
function frontendContractSourceFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

it('merender halaman Inertia yang filenya benar-benar ada', function () {
    $missing = [];

    foreach (frontendContractSourceFiles() as $path) {
        $content = file_get_contents($path);

        if (! preg_match_all("/Inertia::render\(\s*'([^']+)'/", $content, $matches)) {
            continue;
        }

        foreach (array_unique($matches[1]) as $component) {
            $page = resource_path('js/pages/'.$component.'.tsx');

            if (! file_exists($page)) {
                $missing[] = $component.' ← '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }
    }

    expect($missing)->toBe([], "Halaman Inertia berikut dirender tetapi file .tsx-nya tidak ada:\n".implode("\n", $missing));
});

it('mendaftarkan komponen Livewire yang view-nya benar-benar ada', function () {
    $missing = [];

    foreach (glob(base_path('routes/web/*.php')) as $routeFile) {
        $content = file_get_contents($routeFile);

        if (! preg_match_all("/Route::livewire\(\s*'[^']+'\s*,\s*'([^']+)'/", $content, $matches)) {
            continue;
        }

        foreach (array_unique($matches[1]) as $component) {
            $parts = explode('.', $component);
            $slug = array_pop($parts);
            $relative = implode('/', $parts).'/⚡'.$slug.'.blade.php';

            $candidates = [
                resource_path('views/components/'.$relative),
                resource_path('views/livewire/'.$relative),
            ];

            $exists = collect($candidates)->contains(fn (string $candidate) => file_exists($candidate));

            if (! $exists) {
                $missing[] = $component.' ← '.basename($routeFile);
            }
        }
    }

    expect($missing)->toBe([], "Komponen Livewire berikut didaftarkan tetapi view-nya tidak ada:\n".implode("\n", $missing));
});

it('tidak mendaftarkan route Livewire untuk halaman yang tidak ada', function () {
    $livewireRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => is_string($route->getName())
            && array_key_exists('livewire_component', $route->getAction()));

    $missingViews = [];
    $deadDeleteRoutes = [];

    foreach ($livewireRoutes as $route) {
        $name = $route->getName();
        $component = $route->getAction('livewire_component');

        // Hapus data dilakukan dari PowerGrid, bukan lewat halaman /delete.
        if (str_ends_with($name, '.delete')) {
            $deadDeleteRoutes[] = $name.' ['.$component.']';
        }

        $parts = explode('.', $component);
        $slug = array_pop($parts);
        $relative = implode('/', $parts).'/⚡'.$slug.'.blade.php';

        $exists = file_exists(resource_path('views/components/'.$relative))
            || file_exists(resource_path('views/livewire/'.$relative));

        if (! $exists) {
            $missingViews[] = $name.' ['.$component.']';
        }
    }

    expect($deadDeleteRoutes)->toBe([], "Route delete Livewire berikut tidak punya halaman:\n".implode("\n", $deadDeleteRoutes));
    expect($missingViews)->toBe([], "Route Livewire berikut tidak punya view:\n".implode("\n", $missingViews));
});
