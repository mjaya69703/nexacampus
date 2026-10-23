<?php

/**
 * Portabilitas query.
 *
 * Produksi memakai MySQL, tetapi test & CI memakai SQLite. Fungsi khusus
 * MySQL seperti FIELD() membuat query berjalan di produksi namun gagal di
 * CI (pernah terjadi pada dashboard financial). Gunakan
 * App\Support\Database\PortableOrder::priority() sebagai gantinya.
 */
it('tidak memakai fungsi SQL MySQL-only di kode aplikasi', function () {
    $offenders = [];

    foreach ([app_path(), resource_path('views')] as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // File helper ini memang menyebut FIELD() di dokumentasi.
            if (str_ends_with(str_replace('\\', '/', $file->getPathname()), 'app/Support/Database/PortableOrder.php')) {
                continue;
            }

            if (preg_match('/\b(FIELD|YEAR|MONTH|IFNULL|GROUP_CONCAT|DATEDIFF|TIMESTAMPDIFF)\s*\(|\bNOW\s*\(\s*\)/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([], "Gunakan PortableOrder/parameter terikat alih-alih fungsi MySQL-only:\n".implode("\n", $offenders));
});
