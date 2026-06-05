<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->string('attendance_method')->default('manual')->after('status');
            $table->string('qr_secret', 80)->nullable()->after('attendance_method');
            $table->unsignedSmallInteger('qr_interval_seconds')->default(2)->after('qr_secret');
            $table->timestamp('opened_at')->nullable()->after('qr_interval_seconds');
            $table->timestamp('closed_at')->nullable()->after('opened_at');
            $table->unsignedSmallInteger('late_after_minutes')->nullable()->after('closed_at');
            $table->boolean('geo_required')->default(false)->after('late_after_minutes');
            $table->boolean('photo_required')->default(false)->after('geo_required');
            $table->index(['status', 'opened_at']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('source')->default('manual_lecturer')->after('status');
            $table->string('verification_status')->default('verified')->after('source');
            $table->timestamp('scanned_at')->nullable()->after('recorded_by');
            $table->decimal('latitude', 10, 7)->nullable()->after('scanned_at');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('location_accuracy', 8, 2)->nullable()->after('longitude');
            $table->string('photo_path')->nullable()->after('location_accuracy');
            $table->string('device_fingerprint')->nullable()->after('photo_path');
            $table->unsignedBigInteger('token_slot')->nullable()->after('device_fingerprint');
            $table->string('ip_address', 64)->nullable()->after('token_slot');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->index(['source', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex(['source', 'verification_status']);
            $table->dropColumn([
                'source',
                'verification_status',
                'scanned_at',
                'latitude',
                'longitude',
                'location_accuracy',
                'photo_path',
                'device_fingerprint',
                'token_slot',
                'ip_address',
                'user_agent',
            ]);
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex(['status', 'opened_at']);
            $table->dropColumn([
                'attendance_method',
                'qr_secret',
                'qr_interval_seconds',
                'opened_at',
                'closed_at',
                'late_after_minutes',
                'geo_required',
                'photo_required',
            ]);
        });
    }
};
