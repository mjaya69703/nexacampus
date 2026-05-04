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
        Schema::create('curriculum_courses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('curriculum_id')
                ->constrained('curriculums')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unsignedTinyInteger('semester_no')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedTinyInteger('credits_override')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['curriculum_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_courses');
    }
};
