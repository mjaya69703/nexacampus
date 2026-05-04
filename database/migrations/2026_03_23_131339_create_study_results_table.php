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
        Schema::create('study_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('study_plan_id')
                ->nullable()
                ->constrained('study_plans')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->unsignedTinyInteger('semester_no')->nullable();

            $table->unsignedTinyInteger('total_courses')->default(0);
            $table->unsignedTinyInteger('total_credits_taken')->default(0);
            $table->unsignedTinyInteger('total_credits_passed')->default(0);

            $table->decimal('semester_gpa', 3, 2)->nullable();     // IPS
            $table->decimal('cumulative_gpa', 3, 2)->nullable();   // IPK snapshot opsional

            $table->enum('status', [
                'Draft',
                'Finalized',
                'Published',
            ])->default('Draft');

            $table->dateTime('finalized_at')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();

            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['student_profile_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_results');
    }
};
