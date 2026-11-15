<?php

namespace App\Support\Database;

class PortableOrder
{
    /**
     * Bangun ekspresi ORDER BY yang mengurutkan kolom sesuai urutan nilai
     * yang diberikan. Pengganti fungsi MySQL-only `FIELD()` agar query yang
     * sama berjalan di MySQL (produksi) maupun SQLite (test/CI).
     *
     * Contoh: priority('status', ['active', 'released']) menghasilkan
     * "CASE status WHEN 'active' THEN 1 WHEN 'released' THEN 2 ELSE 3 END".
     *
     * @param  array<int, string>  $values
     */
    public static function priority(string $column, array $values): string
    {
        $values = array_values($values);

        if ($values === []) {
            return '1';
        }

        $cases = [];

        foreach ($values as $index => $value) {
            $cases[] = "WHEN '".str_replace("'", "''", $value)."' THEN ".($index + 1);
        }

        return "CASE {$column} ".implode(' ', $cases).' ELSE '.(count($values) + 1).' END';
    }
}
