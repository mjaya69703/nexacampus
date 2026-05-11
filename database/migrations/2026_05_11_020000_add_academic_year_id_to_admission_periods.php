<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('admission_periods', 'academic_year_id')) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->after('code')
                    ->constrained('academic_years')
                    ->nullOnDelete();

                $table->index(['academic_year_id', 'is_active'], 'adm_period_year_active_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admission_periods', function (Blueprint $table) {
            if (Schema::hasColumn('admission_periods', 'academic_year_id')) {
                $table->dropConstrainedForeignId('academic_year_id');
            }
        });
    }
};
