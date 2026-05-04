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
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('study_program_id')
                ->constrained('study_programs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('curriculum_id')
                ->nullable()
                ->constrained('curriculums')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('label')->nullable(); // contoh: Reguler Pagi A / Karyawan / Beasiswa
            $table->string('code', 100)->nullable();

            $table->unsignedTinyInteger('semester_no')->nullable();
            $table->unsignedInteger('capacity')->nullable();

            $table->unsignedTinyInteger('credits')->nullable();
            $table->boolean('is_required')->default(true);

            $table->enum('delivery_mode', ['Offline', 'Online', 'Hybrid'])->default('Offline');

            $table->enum('status', ['Draft', 'Open', 'Closed', 'Cancelled'])->default('Draft');

            // $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique([
                'academic_year_id',
                'study_program_id',
                'course_id',
                'label',
            ], 'course_offerings_unique_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
