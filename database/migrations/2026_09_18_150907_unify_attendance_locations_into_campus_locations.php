<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unifikasi lokasi absensi ke campus_locations (Batch-O0).
     *
     * - campus_locations += is_attendance_point
     * - tiap baris employee_attendance_locations digabung ke campus row yang
     *   cocok (jarak <100m + irisan kata nama) atau dibuatkan baris baru
     * - FK riwayat absensi (check_in/out_location_id) dipetakan ulang
     * - tabel employee_attendance_locations dihapus
     *
     * down() memulihkan STRUKTUR saja; pemetaan data tidak reversible.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('campus_locations', 'is_attendance_point')) {
            Schema::table('campus_locations', function (Blueprint $table) {
                $table->boolean('is_attendance_point')->default(false)->after('is_main');
            });
        }

        // Tanpa DB::transaction pembungkus: DDL MySQL auto-commit dan akan
        // memutus transaksi ("There is no active transaction" saat commit).
        // Guard hasTable membuat eksekusi ulang aman (langkah selesai dilewati).
        if (Schema::hasTable('employee_attendance_locations')) {
            $map = $this->buildMap();

            $this->dropForeignKey('employee_attendance_records', 'check_in_location_id');
            $this->dropForeignKey('employee_attendance_records', 'check_out_location_id');

            foreach (['check_in_location_id', 'check_out_location_id'] as $column) {
                DB::table('employee_attendance_records')
                    ->whereNotNull($column)
                    ->orderBy('id')
                    ->chunkById(500, function ($records) use ($column, $map) {
                        foreach ($records as $record) {
                            if (! isset($map[$record->{$column}])) {
                                continue;
                            }

                            DB::table('employee_attendance_records')
                                ->where('id', $record->id)
                                ->update([$column => $map[$record->{$column}]]);
                        }
                    });
            }

            Schema::dropIfExists('employee_attendance_locations');
        }

        // FK riwayat kini menunjuk campus_locations (guarded agar re-run aman).
        $this->addForeignKey('employee_attendance_records', 'check_in_location_id', 'campus_locations');
        $this->addForeignKey('employee_attendance_records', 'check_out_location_id', 'campus_locations');
    }

    /**
     * Petakan tiap baris attendance lama ke campus id (murni DML).
     *
     * @return array<int, int> old_id => campus_id
     */
    private function buildMap(): array
    {
        $map = [];

        foreach (DB::table('employee_attendance_locations')->orderBy('id')->get() as $row) {
            $match = $this->findCampusMatch($row);

            if ($match) {
                DB::table('campus_locations')->where('id', $match->id)->update([
                    'is_attendance_point' => true,
                    'updated_at' => now(),
                ]);
                $map[$row->id] = $match->id;
                continue;
            }

            $map[$row->id] = DB::table('campus_locations')->insertGetId([
                'name' => $row->name,
                'code' => $this->uniqueCampusCode($row->code),
                'address' => $row->address,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'radius_meters' => $row->radius_meters ?? 100,
                'is_main' => false,
                'is_attendance_point' => true,
                'is_active' => (bool) $row->is_active,
                'created_by' => $row->created_by,
                'updated_by' => $row->updated_by,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }

        return $map;
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        try {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (($foreignKey['columns'] ?? []) === [$column]) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return true;
        }

        return false;
    }

    private function dropForeignKey(string $table, string $column): void
    {
        if (! $this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });
    }

    private function addForeignKey(string $table, string $column, string $references): void
    {
        if ($this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column, $references) {
            $table->foreign($column)->references('id')->on($references)->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::create('employee_attendance_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->dropForeignKey('employee_attendance_records', 'check_in_location_id');
        $this->dropForeignKey('employee_attendance_records', 'check_out_location_id');
        $this->addForeignKey('employee_attendance_records', 'check_in_location_id', 'employee_attendance_locations');
        $this->addForeignKey('employee_attendance_records', 'check_out_location_id', 'employee_attendance_locations');

        Schema::table('campus_locations', function (Blueprint $table) {
            $table->dropColumn('is_attendance_point');
        });
    }

    private function findCampusMatch(object $row): ?object
    {
        $candidates = DB::table('campus_locations')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'name', 'latitude', 'longitude']);

        $rowWords = $this->words($row->name);

        foreach ($candidates as $candidate) {
            if ($this->distanceMeters(
                (float) $row->latitude, (float) $row->longitude,
                (float) $candidate->latitude, (float) $candidate->longitude
            ) > 100) {
                continue;
            }

            if (count(array_intersect($rowWords, $this->words($candidate->name))) > 0) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Kata signifikan nama (huruf kecil, abaikan kata generik).
     */
    private function words(?string $name): array
    {
        $stopwords = ['kampus', 'gedung', 'utama', 'cabang', 'dan', 'di', 'the', 'of'];

        return array_values(array_filter(
            preg_split('/[^a-z0-9]+/', strtolower((string) $name)) ?: [],
            fn ($word) => strlen($word) > 2 && ! in_array($word, $stopwords, true)
        ));
    }

    private function uniqueCampusCode(?string $code): string
    {
        $base = substr(strtoupper(trim((string) $code) ?: 'ATT'), 0, 44);
        $candidate = substr($base, 0, 50);
        $suffix = 1;

        while (DB::table('campus_locations')->where('code', $candidate)->exists()) {
            $suffix++;
            $candidate = substr($base, 0, 50 - strlen((string) $suffix) - 1).'-'.$suffix;
        }

        return $candidate;
    }

    private function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
};
