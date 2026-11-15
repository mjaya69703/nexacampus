<?php

use Illuminate\Support\Facades\Schema;

/**
 * Portabilitas query.
 *
 * Produksi memakai MySQL, tetapi test & CI memakai SQLite. Fungsi khusus
 * MySQL seperti FIELD() membuat query berjalan di produksi namun gagal di
 * CI (pernah terjadi pada dashboard financial). Gunakan
 * App\Support\Database\PortableOrder::priority() atau
 * App\Support\Database\PortableConcat::fullName() sebagai gantinya.
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

            // Helper portabilitas memang menyebut fungsi MySQL di dokumentasi.
            if (str_contains(str_replace('\\', '/', $file->getPathname()), 'app/Support/Database/')) {
                continue;
            }

            if (preg_match('/\b(FIELD|YEAR|MONTH|IFNULL|GROUP_CONCAT|DATEDIFF|TIMESTAMPDIFF|CONCAT)\s*\(|\bNOW\s*\(\s*\)/', file_get_contents($file->getPathname()))) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($offenders)->toBe([], "Gunakan PortableOrder/parameter terikat alih-alih fungsi MySQL-only:\n".implode("\n", $offenders));
});

it('menjaga nama index dan foreign key di bawah batas identifier MySQL', function () {
    $offenders = [];

    foreach (Schema::getTableListing() as $table) {
        foreach (Schema::getIndexes($table) as $index) {
            $name = $index['name'] ?? '';

            if (strlen($name) > 64) {
                $offenders[] = "{$table} [index] {$name} (".strlen($name).' karakter)';
            }
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $name = $foreignKey['name'] ?? '';

            if (strlen($name) > 64) {
                $offenders[] = "{$table} [foreign key] {$name} (".strlen($name).' karakter)';
            }
        }
    }

    expect($offenders)->toBe([], "Nama index/foreign key melebihi 64 karakter sehingga gagal di MySQL:\n".implode("\n", $offenders));
});
