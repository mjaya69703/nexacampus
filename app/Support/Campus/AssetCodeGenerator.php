<?php

namespace App\Support\Campus;

use App\Models\Campus\CampusAsset;
use Illuminate\Support\Facades\DB;

/**
 * Nomor inventaris urut per tahun: AST-2026-001, ... (ganti generator acak).
 * Counter per tahun + lockForUpdate agar klik ganda/bulk aman.
 * Kode lama (acak) tetap valid — generator hanya untuk nomor baru.
 */
class AssetCodeGenerator
{
    public static function next(): string
    {
        return DB::transaction(function () {
            $year = now()->format('Y');

            $counter = DB::table('asset_number_counters')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $next = ($counter->last_number ?? 0) + 1;

            if ($counter) {
                DB::table('asset_number_counters')
                    ->where('year', $year)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                DB::table('asset_number_counters')->insert([
                    'year' => $year, 'last_number' => $next,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $code = sprintf('AST-%s-%03d', $year, $next);

            if (CampusAsset::where('asset_code', $code)->exists()) {
                return static::next();
            }

            return $code;
        }, 3);
    }
}
