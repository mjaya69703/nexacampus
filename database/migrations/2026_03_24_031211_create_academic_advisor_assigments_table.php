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
        Schema::create('academic_advisor_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('lecturer_profile_id')
                ->constrained('lecturer_profiles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['student_profile_id', 'academic_year_id'], 'advisor_assignment_student_year_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_advisor_assignments');
    }
};
