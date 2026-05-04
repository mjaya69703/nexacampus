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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendance_session_id')
                ->constrained('attendance_sessions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('student_profile_id')
                ->constrained('student_profiles')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->enum('status', ['Present', 'Absent', 'Excused', 'Sick', 'Late'])
                ->default('Absent');

            $table->dateTime('recorded_at')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(['attendance_session_id', 'student_profile_id'], 'attendance_records_unique_session_student');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
