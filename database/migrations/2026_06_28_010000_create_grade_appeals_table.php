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
        Schema::create('grade_appeals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_grade_id')
                ->constrained('student_grades')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('lecturer_profile_id')
                ->nullable()
                ->constrained('lecturer_profiles')
                ->nullOnDelete();

            $table->foreignId('student_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', ['submitted', 'under_review', 'approved', 'rejected', 'closed'])
                ->default('submitted');

            $table->string('reason_category', 80);
            $table->text('reason');
            $table->text('expected_outcome')->nullable();
            $table->text('lecturer_response')->nullable();
            $table->decimal('original_score', 5, 2)->nullable();
            $table->decimal('requested_score', 5, 2)->nullable();
            $table->decimal('resolved_score', 5, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['student_profile_id', 'status']);
            $table->index(['lecturer_profile_id', 'status']);
            $table->index(['course_offering_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_appeals');
    }
};
