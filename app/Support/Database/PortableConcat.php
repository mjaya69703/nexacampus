<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

class PortableConcat
{
    /**
     * Bangun ekspresi konkatenasi string yang portabel antar driver.
     * MySQL/MariaDB memakai CONCAT(), SQL Server memakai operator +,
     * sedangkan SQLite/PostgreSQL memakai operator ||. Query yang sama
     * karenanya berjalan di MySQL (produksi) maupun SQLite (test/CI).
     *
     * @param  array<int, string>  $expressions  Ekspresi SQL mentah, bukan input user.
     */
    public static function of(array $expressions): string
    {
        if ($expressions === []) {
            return "''";
        }

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'CONCAT('.implode(', ', $expressions).')',
            'sqlsrv' => implode(' + ', $expressions),
            default => implode(' || ', $expressions),
        };
    }

    /**
     * Ekspresi nama lengkap: nama depan + spasi + nama belakang.
     */
    public static function fullName(string $firstName = 'first_name', string $lastName = 'last_name'): string
    {
        return self::of([$firstName, "' '", $lastName]);
    }
}
