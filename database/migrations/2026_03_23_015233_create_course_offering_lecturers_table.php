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
        Schema::create('course_offering_lecturers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('lecturer_profile_id')
                ->constrained('lecturer_profiles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->enum('role', ['Coordinator', 'Primary', 'Secondary', 'Assistant'])
                ->default('Primary');

            // $table->boolean('is_responsible')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['course_offering_id', 'lecturer_profile_id'], 'course_offering_lecturers_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_offering_lecturers');
    }
};
