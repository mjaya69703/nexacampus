<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Konfigurator widget dashboard admin (Livewire) sudah dibuang;
        // personalisasi kini ditangani permission via sections[] adaptif.
        if (Schema::hasColumn('users', 'dashboard_widget_config')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('dashboard_widget_config');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'dashboard_widget_config')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('dashboard_widget_config')->nullable()->after('remember_token');
            });
        }
    }
};
