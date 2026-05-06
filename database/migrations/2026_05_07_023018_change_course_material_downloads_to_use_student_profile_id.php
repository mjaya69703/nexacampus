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
        Schema::table('course_material_downloads', function (Blueprint $table) {
            // Drop old foreign key and column
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
            
            // Add new column with correct reference
            $table->foreignId('student_profile_id')
                ->after('course_material_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            
            // Recreate unique index with custom name to avoid MySQL 64 char limit
            $table->unique(['course_material_id', 'student_profile_id'], 'cm_downloads_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_material_downloads', function (Blueprint $table) {
            // Drop new foreign key and column
            $table->dropForeign(['student_profile_id']);
            $table->dropUnique('cm_downloads_unique');
            $table->dropColumn('student_profile_id');
            
            // Restore old column
            $table->foreignId('student_id')
                ->after('course_material_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            
            // Restore old unique index
            $table->unique(['course_material_id', 'student_id']);
        });
    }
};
