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
        Schema::create('course_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('lecturer_profile_id')
                ->nullable()
                ->constrained('lecturer_profiles')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('room')->nullable();
            $table->string('building')->nullable();

            $table->enum('day_of_week', [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday',
            ]);

            $table->time('start_time');
            $table->time('end_time');

            $table->enum('session_type', [
                'Lecture',
                'Practicum',
                'Tutorial',
                'Exam',
                'Custom',
            ])->default('Lecture');

            $table->enum('delivery_mode', [
                'Offline',
                'Online',
                'Hybrid',
            ])->default('Offline');

            $table->string('meeting_link')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_schedules');
    }
};
