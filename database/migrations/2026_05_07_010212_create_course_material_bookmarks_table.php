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
        Schema::create('course_material_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_material_id')
                ->constrained('course_materials')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            // Custom unique index name to avoid MySQL 64 char limit
            $table->unique(['course_material_id', 'student_profile_id'], 'cm_bookmarks_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_material_bookmarks');
    }
};
