<?php

/**
 * Konfigurasi cache.
 *
 * Saat `php artisan config:cache` dijalankan (kebiasaan deploy produksi),
 * file .env tidak lagi dibaca. Pemanggilan env() di luar folder config
 * karenanya mengembalikan null dan opsi konfigurasi diam-diam diabaikan.
 */
it('tidak memanggil env() di luar folder config', function () {
    $offenders = [];

    foreach ([app_path(), base_path('routes'), resource_path('views')] as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match('/(?<![\\\\\w:])env\s*\(/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([], "Gunakan config() alih-alih env() agar aman saat config di-cache:\n".implode("\n", $offenders));
});
