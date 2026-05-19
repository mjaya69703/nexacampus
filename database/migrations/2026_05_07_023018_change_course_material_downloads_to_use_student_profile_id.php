<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('course_material_downloads', 'student_profile_id')) {
            return;
        }

        Schema::table('course_material_downloads', function (Blueprint $table) {
            if (Schema::hasColumn('course_material_downloads', 'student_id')) {
                $table->dropUnique('course_material_downloads_course_material_id_student_id_unique');
                $table->dropForeign(['student_id']);
                $table->dropColumn('student_id');
            }

            $table->foreignId('student_profile_id')
                ->after('course_material_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(['course_material_id', 'student_profile_id'], 'cm_downloads_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('course_material_downloads', 'student_profile_id')) {
            return;
        }

        Schema::table('course_material_downloads', function (Blueprint $table) {
            $table->dropUnique('cm_downloads_unique');
            $table->dropForeign(['student_profile_id']);
            $table->dropColumn('student_profile_id');

            if (Schema::hasColumn('course_material_downloads', 'student_id')) {
                return;
            }

            $table->foreignId('student_id')
                ->after('course_material_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->unique(['course_material_id', 'student_id']);
        });
    }
};
