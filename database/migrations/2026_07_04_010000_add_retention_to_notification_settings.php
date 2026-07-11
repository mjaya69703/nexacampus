<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('log_retention_days')->default(90)->after('timeout_seconds');
            $table->unsignedSmallInteger('provider_response_retention_days')->default(30)->after('log_retention_days');
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'log_retention_days',
                'provider_response_retention_days',
            ]);
        });
    }
};
