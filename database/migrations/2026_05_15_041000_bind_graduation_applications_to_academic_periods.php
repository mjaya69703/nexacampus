<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('graduation_applications', 'academic_period_id')) {
            return;
        }

        Schema::table('graduation_applications', function (Blueprint $table) {
            $table->foreignId('academic_period_id')
                ->nullable()
                ->after('student_profile_id')
                ->constrained('academic_periods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('graduation_applications', 'academic_period_id')) {
            return;
        }

        Schema::table('graduation_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_period_id');
        });
    }
};
