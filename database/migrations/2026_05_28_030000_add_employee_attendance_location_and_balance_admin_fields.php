<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

            $table->index(['is_active', 'code']);
        });

        Schema::table('employee_attendance_records', function (Blueprint $table) {
            $table->foreignId('check_in_location_id')->nullable()->after('work_minutes')->constrained('employee_attendance_locations')->nullOnDelete();
            $table->foreignId('check_out_location_id')->nullable()->after('check_in_location_id')->constrained('employee_attendance_locations')->nullOnDelete();
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('check_out_location_id');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_in_longitude');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->unsignedInteger('check_in_accuracy_meters')->nullable()->after('check_out_longitude');
            $table->unsignedInteger('check_out_accuracy_meters')->nullable()->after('check_in_accuracy_meters');
            $table->unsignedInteger('check_in_distance_meters')->nullable()->after('check_out_accuracy_meters');
            $table->unsignedInteger('check_out_distance_meters')->nullable()->after('check_in_distance_meters');
            $table->string('location_status')->default('unverified')->after('check_out_distance_meters');
            $table->string('check_in_photo_path')->nullable()->after('location_status');
            $table->string('check_out_photo_path')->nullable()->after('check_in_photo_path');
        });

        if (Schema::hasTable('work_units')) {
            $now = now();
            DB::table('employee_attendance_locations')->insertOrIgnore([
                [
                    'name' => 'Kampus Utama',
                    'code' => 'MAIN_CAMPUS',
                    'address' => 'Lokasi demo kampus utama. Ubah koordinat sesuai lokasi kantor/kampus asli.',
                    'latitude' => -6.2000000,
                    'longitude' => 106.8166660,
                    'radius_meters' => 150,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('check_in_location_id');
            $table->dropConstrainedForeignId('check_out_location_id');
            $table->dropColumn([
                'check_in_latitude',
                'check_in_longitude',
                'check_out_latitude',
                'check_out_longitude',
                'check_in_accuracy_meters',
                'check_out_accuracy_meters',
                'check_in_distance_meters',
                'check_out_distance_meters',
                'location_status',
                'check_in_photo_path',
                'check_out_photo_path',
            ]);
        });

        Schema::dropIfExists('employee_attendance_locations');
    }
};
