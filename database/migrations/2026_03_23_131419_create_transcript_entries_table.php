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
        Schema::create('transcript_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('student_grade_id')
                ->nullable()
                ->constrained('student_grades')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unsignedTinyInteger('semester_no')->nullable();

            $table->unsignedTinyInteger('credits')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('letter_grade', 5)->nullable();
            $table->decimal('grade_point', 3, 2)->nullable();

            $table->enum('result_status', [
                'Passed',
                'Failed',
                'Incomplete',
                'Withdrawn',
                'Cancelled',
            ])->nullable();

            $table->boolean('is_counted_in_gpa')->default(true);
            $table->boolean('is_best_grade')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['student_profile_id', 'course_id', 'student_grade_id'], 'transcript_entries_unique_grade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcript_entries');
    }
};
